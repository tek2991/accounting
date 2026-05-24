<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Payment;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\DiscountType;
use Exception;

class BillService
{
    public function __construct(
        private TransactionService $txnService,
        private DocumentNumberService $docNumberService,
        private PostingGuard $postingGuard,
    ) {}

    public function create(int $companyId, array $data): Bill
    {
        return DB::transaction(function () use ($companyId, $data) {
            $bill = new Bill($data);
            $bill->company_id = $companyId;
            $bill->bill_number = $this->docNumberService->nextBillNumber($companyId);
            $bill->status = BillStatus::Draft;
            $bill->save();

            return $bill;
        });
    }

    public function recalculateTotals(Bill $bill): void
    {
        $subtotal = 0;
        $taxTotal = 0;
        $discountAmount = 0;

        foreach ($bill->items as $item) {
            // Line total = qty * unit_price
            $baseLineTotal = $item->getRawOriginal('quantity') * $item->getRawOriginal('unit_price');
            
            // Item discount
            $itemDiscount = 0;
            if ($item->discount_type === DiscountType::Percentage) {
                $itemDiscount = $baseLineTotal * ($item->getRawOriginal('discount_rate') / 100);
            } elseif ($item->discount_type === DiscountType::Fixed) {
                $itemDiscount = $item->getRawOriginal('discount_amount');
            }
            $itemLineTotal = $baseLineTotal - $itemDiscount;
            
            $item->line_total = $itemLineTotal / 100;
            $item->tax_amount = $item->getRawOriginal('tax_amount') / 100; 
            
            $item->save();

            $subtotal += $itemLineTotal;
            $taxTotal += $item->getRawOriginal('tax_amount');
        }

        $bill->subtotal = $subtotal / 100;
        
        // Bill discount
        if ($bill->discount_type === DiscountType::Percentage) {
            $discountAmount = $subtotal * ($bill->getRawOriginal('discount_rate') / 100);
        } elseif ($bill->discount_type === DiscountType::Fixed) {
            $discountAmount = $bill->getRawOriginal('discount_amount');
        }

        $bill->discount_amount = $discountAmount / 100;
        $bill->tax_total = $taxTotal / 100;
        
        $grandTotal = $subtotal - $discountAmount + $taxTotal;
        $bill->grand_total = $grandTotal / 100;
        
        $balanceDue = $grandTotal - $bill->getRawOriginal('amount_paid');
        $bill->balance_due = $balanceDue / 100;

        $bill->save();
    }

    public function post(Bill $bill): void
    {
        DB::transaction(function () use ($bill) {
            $bill = Bill::lockForUpdate()->find($bill->id);
            
            if ($bill->transaction_id !== null) {
                return; // Idempotent
            }
            
            $this->postingGuard->assertBillPostable($bill);

            $payableAccountId = $bill->contact->payable_account_id;
            if (!$payableAccountId) {
                throw new Exception("Contact must have a payable account to post bill.");
            }

            $entries = [];

            // CR: Payable Account
            $entries[] = [
                'account_id' => $payableAccountId,
                'debit' => 0,
                'credit' => $bill->getRawOriginal('grand_total'),
                'description' => "Bill {$bill->bill_number}",
            ];

            // DR: Expense Accounts & Taxes from items
            $expenseAccounts = [];
            $taxAccounts = [];

            foreach ($bill->items as $item) {
                $expAccountId = $item->expense_account_id ?? $bill->default_expense_account_id;
                if (!$expAccountId) {
                    throw new Exception("Missing expense account for item.");
                }

                if (!isset($expenseAccounts[$expAccountId])) {
                    $expenseAccounts[$expAccountId] = 0;
                }
                $expenseAccounts[$expAccountId] += $item->getRawOriginal('line_total');

                // Aggregate taxes from snapshot
                if ($item->tax_snapshot) {
                    foreach ($item->tax_snapshot as $taxComp) {
                        $taxAccId = $taxComp['account_id'];
                        if (!isset($taxAccounts[$taxAccId])) {
                            $taxAccounts[$taxAccId] = 0;
                        }
                        $taxAccounts[$taxAccId] += $taxComp['amount'];
                    }
                }
            }

            foreach ($expenseAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Bill {$bill->bill_number} Expense",
                    ];
                }
            }

            foreach ($taxAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Bill {$bill->bill_number} Tax",
                    ];
                }
            }

            $transaction = $this->txnService->createTransaction(
                $bill->company_id,
                $bill->issue_date,
                "Posted Bill {$bill->bill_number}",
                "Bill", // Technically should be BillPosting if we had it, but string for now
                $entries
            );

            $bill->transaction_id = $transaction->id;
            $bill->status = BillStatus::Received;
            $bill->save();
            
            activity('financial')
                ->performedOn($bill)
                ->event('bill.posted')
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'grand_total' => $bill->getRawOriginal('grand_total')
                ])
                ->log("Bill {$bill->bill_number} posted");
        });
    }

    public function recordPayment(Bill $bill, array $paymentData): Payment
    {
        return DB::transaction(function () use ($bill, $paymentData) {
            $bill = Bill::lockForUpdate()->find($bill->id);
            $this->postingGuard->assertPaymentAllowed($bill);
            
            $paymentAmount = $paymentData['amount'];

            // CR: Payment Bank Account
            // DR: Payable Account
            $entries = [
                [
                    'account_id' => $paymentData['payment_account_id'],
                    'debit' => 0,
                    'credit' => $paymentAmount,
                    'description' => "Payment for Bill {$bill->bill_number}",
                ],
                [
                    'account_id' => $bill->contact->payable_account_id,
                    'debit' => $paymentAmount,
                    'credit' => 0,
                    'description' => "Payment for Bill {$bill->bill_number}",
                ],
            ];

            $transaction = $this->txnService->createTransaction(
                $bill->company_id,
                $paymentData['payment_date'],
                "Payment against Bill {$bill->bill_number}",
                "Payment",
                $entries
            );

            $payment = new Payment($paymentData);
            $payment->company_id = $bill->company_id;
            $payment->transaction_id = $transaction->id;
            $bill->payments()->save($payment);

            // Update bill amounts
            $newPaid = $bill->getRawOriginal('amount_paid') + $paymentAmount;
            $newBalance = $bill->getRawOriginal('grand_total') - $newPaid;

            $bill->amount_paid = $newPaid / 100;
            $bill->balance_due = $newBalance / 100;

            if ($newBalance <= 0) {
                $bill->status = BillStatus::Paid;
            } else {
                $bill->status = BillStatus::PartiallyPaid;
            }
            
            $bill->save();
            
            activity('financial')
                ->performedOn($bill)
                ->event('bill.payment_made')
                ->withProperties([
                    'payment_id' => $payment->id,
                    'amount' => $paymentAmount,
                    'balance_due_after' => $newBalance
                ])
                ->log("Payment of " . number_format($paymentAmount / 100, 2) . " made");

            return $payment;
        });
    }

    public function cancel(Bill $bill): void
    {
        DB::transaction(function () use ($bill) {
            $bill = Bill::lockForUpdate()->find($bill->id);
            $this->postingGuard->assertCancellable($bill);
            
            // Reverse transaction
            if ($bill->transaction_id) {
                $this->txnService->deleteTransaction($bill->transaction);
            }

            foreach ($bill->payments as $payment) {
                if ($payment->transaction_id) {
                    $this->txnService->deleteTransaction($payment->transaction);
                }
                $payment->delete();
            }

            $bill->status = BillStatus::Cancelled;
            $bill->amount_paid = 0;
            $bill->balance_due = 0;
            $bill->save();
            
            activity('financial')
                ->performedOn($bill)
                ->event('bill.cancelled')
                ->log("Bill {$bill->bill_number} cancelled");
        });
    }
}
