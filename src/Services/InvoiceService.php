<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Payment;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Enums\DiscountType;
use Tek2991\Accounting\Models\JournalEntry;
use Exception;

class InvoiceService
{
    public function __construct(
        private TransactionService $txnService,
        private DocumentNumberService $docNumberService,
        private PostingGuard $postingGuard,
    ) {}

    public function create(int $companyId, array $data): Invoice
    {
        return DB::transaction(function () use ($companyId, $data) {
            $invoice = new Invoice($data);
            $invoice->company_id = $companyId;
            $invoice->invoice_number = $this->docNumberService->nextInvoiceNumber($companyId);
            $invoice->status = InvoiceStatus::Draft;
            $invoice->save();

            return $invoice;
        });
    }

    public function recalculateTotals(Invoice $invoice): void
    {
        $subtotal = 0;
        $taxTotal = 0;
        $discountAmount = 0;

        foreach ($invoice->items as $item) {
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
            
            $item->line_total = $itemPreTaxTotal / 100; // Accessor handles minor units
            $item->tax_amount = $itemTaxAmount / 100;
            
            $item->save();

            $subtotal += $itemPreTaxTotal;
            $taxTotal += $itemTaxAmount;
        }

        $invoice->subtotal = $subtotal / 100;
        
        // Invoice discount
        if ($invoice->discount_type === DiscountType::Percentage) {
            $discountAmount = $subtotal * ($invoice->getRawOriginal('discount_rate') / 100);
        } elseif ($invoice->discount_type === DiscountType::Fixed) {
            $discountAmount = $invoice->getRawOriginal('discount_amount');
        }

        $invoice->discount_amount = $discountAmount / 100;
        $invoice->tax_total = $taxTotal / 100;
        
        $grandTotal = $subtotal - $discountAmount + $taxTotal;
        $invoice->grand_total = $grandTotal / 100;
        
        $balanceDue = $grandTotal - $invoice->getRawOriginal('amount_paid');
        $invoice->balance_due = $balanceDue / 100;

        $invoice->save();
    }

    public function post(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = Invoice::lockForUpdate()->find($invoice->id);
            
            if ($invoice->transaction_id !== null) {
                return; // Idempotent
            }
            
            $this->postingGuard->assertInvoicePostable($invoice);

            // 1. Create Journal Entries
            $receivableAccountId = $invoice->contact->receivable_account_id ?? \Tek2991\Accounting\Models\Account::where('company_id', $invoice->company_id)
                ->where('category', \Tek2991\Accounting\Enums\AccountCategory::Asset)
                ->where('default', true)
                ->where('name', 'Accounts Receivable')
                ->value('id');

            if (!$receivableAccountId) {
                throw new \Exception("Cannot post invoice: No receivable account found for customer and no default Accounts Receivable exists.");
            }

            $entries = [];

            // DR: Receivable Account
            $entries[] = [
                'account_id' => $receivableAccountId,
                'type' => 'debit',
                'amount' => $invoice->getRawOriginal('grand_total'),
                'description' => "Invoice {$invoice->invoice_number}",
            ];

            // CR: Income Accounts & Taxes from items
            $incomeAccounts = [];
            $taxAccounts = [];

            foreach ($invoice->items as $item) {
                $incAccountId = $item->income_account_id ?? $invoice->default_income_account_id;
                if (!$incAccountId) {
                    throw new Exception("Missing income account for item.");
                }

                if (!isset($incomeAccounts[$incAccountId])) {
                    $incomeAccounts[$incAccountId] = 0;
                }
                $incomeAccounts[$incAccountId] += $item->getRawOriginal('line_total');

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

            foreach ($incomeAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'credit',
                        'amount' => $amount,
                        'description' => "Invoice {$invoice->invoice_number} Revenue",
                    ];
                }
            }

            foreach ($taxAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'credit',
                        'amount' => $amount,
                        'description' => "Invoice {$invoice->invoice_number} Tax",
                    ];
                }
            }

            $transaction = $this->txnService->createTransaction([
                'company_id' => $invoice->company_id,
                'posted_at' => $invoice->issue_date,
                'description' => "Posted Invoice {$invoice->invoice_number}",
                'type' => \Tek2991\Accounting\Enums\TransactionType::InvoicePosting,
                'reference' => $invoice->invoice_number,
                'reviewed' => false,
                'pending' => false,
            ], $entries);

            $invoice->transaction_id = $transaction->id;
            $invoice->status = InvoiceStatus::Sent;
            $invoice->save();
            
            activity('financial')
                ->performedOn($invoice)
                ->event('invoice.posted')
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'grand_total' => $invoice->getRawOriginal('grand_total')
                ])
                ->log("Invoice {$invoice->invoice_number} posted");
        });
    }

    public function recordPayment(Invoice $invoice, array $paymentData): Payment
    {
        return DB::transaction(function () use ($invoice, $paymentData) {
            $invoice = Invoice::lockForUpdate()->find($invoice->id);
            $this->postingGuard->assertPaymentAllowed($invoice);
            
            $paymentAmount = $paymentData['amount'];

            $receivableAccountId = $invoice->contact->receivable_account_id ?? \Tek2991\Accounting\Models\Account::where('company_id', $invoice->company_id)
                ->where('category', \Tek2991\Accounting\Enums\AccountCategory::Asset)
                ->where('default', true)
                ->where('name', 'Accounts Receivable')
                ->value('id');

            if (!$receivableAccountId) {
                throw new \Exception("Cannot record payment: No receivable account found for customer and no default Accounts Receivable exists.");
            }

            // DR: Payment Bank Account
            // CR: Receivable Account
            $entries = [
                [
                    'account_id' => $paymentData['payment_account_id'],
                    'type' => 'debit',
                    'amount' => $paymentAmount,
                    'description' => "Payment for Invoice {$invoice->invoice_number}",
                ],
                [
                    'account_id' => $receivableAccountId,
                    'type' => 'credit',
                    'amount' => $paymentAmount,
                    'description' => "Payment for Invoice {$invoice->invoice_number}",
                ],
            ];

            $transaction = $this->txnService->createTransaction([
                'company_id' => $invoice->company_id,
                'posted_at' => $paymentData['payment_date'],
                'description' => "Payment against Invoice {$invoice->invoice_number}",
                'type' => \Tek2991\Accounting\Enums\TransactionType::PaymentIn,
                'reference' => "PAY-{$invoice->invoice_number}",
                'reviewed' => false,
                'pending' => false,
            ], $entries);

            $payment = new Payment($paymentData);
            $payment->company_id = $invoice->company_id;
            $payment->transaction_id = $transaction->id;
            $invoice->payments()->save($payment);

            // Update invoice amounts
            $newPaid = $invoice->getRawOriginal('amount_paid') + $paymentAmount;
            $newBalance = $invoice->getRawOriginal('grand_total') - $newPaid;

            $invoice->amount_paid = $newPaid / 100;
            $invoice->balance_due = $newBalance / 100;

            if ($newBalance <= 0) {
                $invoice->status = InvoiceStatus::Paid;
            } else {
                $invoice->status = InvoiceStatus::PartiallyPaid;
            }
            
            $invoice->save();
            
            activity('financial')
                ->performedOn($invoice)
                ->event('invoice.payment_recorded')
                ->withProperties([
                    'payment_id' => $payment->id,
                    'amount' => $paymentAmount,
                    'balance_due_after' => $newBalance
                ])
                ->log("Payment of " . number_format($paymentAmount / 100, 2) . " recorded");

            return $payment;
        });
    }

    public function cancel(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = Invoice::lockForUpdate()->find($invoice->id);
            $this->postingGuard->assertCancellable($invoice);
            
            // Reverse transaction
            if ($invoice->transaction_id) {
                // Simplified reversal: In a real system, you'd create a reversing transaction.
                // For MVP, we might just mark transaction as cancelled if supported, or create reverse entries.
                // Assuming TransactionService has a reverse method, or we just delete it.
                $this->txnService->deleteTransaction($invoice->transaction);
            }

            foreach ($invoice->payments as $payment) {
                if ($payment->transaction_id) {
                    $this->txnService->deleteTransaction($payment->transaction);
                }
                $payment->delete();
            }

            $invoice->status = InvoiceStatus::Cancelled;
            $invoice->amount_paid = 0;
            $invoice->balance_due = 0;
            $invoice->save();
            
            activity('financial')
                ->performedOn($invoice)
                ->event('invoice.cancelled')
                ->log("Invoice {$invoice->invoice_number} cancelled");
        });
    }
}
