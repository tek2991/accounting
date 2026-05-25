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
            $discountedLineTotal = $baseLineTotal - $itemDiscount;
            
            // Calculate Tax
            $itemTaxAmount = 0;
            $isInclusive = false;
            
            if ($item->tax_id) {
                $tax = \Tek2991\Accounting\Models\Tax::with('components')->find($item->tax_id);
                if ($tax) {
                    $isInclusive = $tax->type === \Tek2991\Accounting\Enums\TaxType::Inclusive;
                    $taxComponents = app(\Tek2991\Accounting\Services\TaxService::class)->calculateTax($discountedLineTotal, $tax);
                    $itemTaxAmount = $taxComponents->sum('amount');
                    $item->tax_snapshot = $taxComponents->toArray();
                }
            }
            
            // Determine item's pre-tax line total
            $itemPreTaxTotal = $isInclusive ? ($discountedLineTotal - $itemTaxAmount) : $discountedLineTotal;
            
            $item->line_total = $itemPreTaxTotal / 100;
            $item->tax_amount = $itemTaxAmount / 100; 
            
            $item->save();

            $subtotal += $itemPreTaxTotal;
            $taxTotal += $itemTaxAmount;
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

            $payableAccountId = $bill->contact->payable_account_id ?? \Tek2991\Accounting\Models\Account::where('company_id', $bill->company_id)
                ->where('category', \Tek2991\Accounting\Enums\AccountCategory::Liability)
                ->where('default', true)
                ->where('name', 'Accounts Payable')
                ->value('id');
                
            if (!$payableAccountId) {
                throw new Exception("Contact must have a payable account to post bill.");
            }

            $entries = [];

            // CR: Payable Account
            $entries[] = [
                'account_id' => $payableAccountId,
                'type' => 'credit',
                'amount' => $bill->getRawOriginal('grand_total'),
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
                        'type' => 'debit',
                        'amount' => $amount,
                        'description' => "Bill {$bill->bill_number} Expense",
                    ];
                }
            }

            foreach ($taxAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'debit',
                        'amount' => $amount,
                        'description' => "Bill {$bill->bill_number} Tax",
                    ];
                }
            }

            $transaction = $this->txnService->createTransaction([
                'company_id' => $bill->company_id,
                'posted_at' => $bill->issue_date,
                'description' => "Posted Bill {$bill->bill_number}",
                'type' => \Tek2991\Accounting\Enums\TransactionType::BillPosting,
                'reference' => $bill->bill_number,
                'reviewed' => false,
                'pending' => false,
            ], $entries);

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

            $payableAccountId = $bill->contact->payable_account_id ?? \Tek2991\Accounting\Models\Account::where('company_id', $bill->company_id)
                ->where('category', \Tek2991\Accounting\Enums\AccountCategory::Liability)
                ->where('default', true)
                ->where('name', 'Accounts Payable')
                ->value('id');

            if (!$payableAccountId) {
                throw new \Exception("Cannot record payment: No payable account found for vendor and no default Accounts Payable exists.");
            }

            // CR: Payment Bank Account
            // DR: Payable Account
            $entries = [
                [
                    'account_id' => $paymentData['payment_account_id'],
                    'type' => 'credit',
                    'amount' => $paymentAmount,
                    'description' => "Payment for Bill {$bill->bill_number}",
                ],
                [
                    'account_id' => $payableAccountId,
                    'type' => 'debit',
                    'amount' => $paymentAmount,
                    'description' => "Payment for Bill {$bill->bill_number}",
                ],
            ];

            $transaction = $this->txnService->createTransaction([
                'company_id' => $bill->company_id,
                'posted_at' => $paymentData['payment_date'],
                'description' => "Payment against Bill {$bill->bill_number}",
                'type' => \Tek2991\Accounting\Enums\TransactionType::PaymentOut,
                'reference' => "PAY-{$bill->bill_number}",
                'reviewed' => false,
                'pending' => false,
            ], $entries);

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
