<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Models\DebitNote;
use Tek2991\Accounting\Enums\DebitNoteStatus;
use Exception;

class DebitNoteService
{
    public function __construct(
        private TransactionService $txnService,
        private DocumentNumberService $docNumberService,
        private PostingGuard $postingGuard,
    ) {}

    public function create(int $companyId, array $data): DebitNote
    {
        return DB::transaction(function () use ($companyId, $data) {
            $dn = new DebitNote($data);
            $dn->company_id = $companyId;
            $dn->debit_note_number = $this->docNumberService->nextDebitNoteNumber($companyId);
            $dn->status = DebitNoteStatus::Draft;
            $dn->save();

            return $dn;
        });
    }

    public function recalculateTotals(DebitNote $dn): void
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($dn->items as $item) {
            // Line total = qty * unit_price
            $qty = ($item->getAttributes()['quantity'] ?? 0);
            if (empty($qty) || $qty == 0) {
                $qty = 1;
            }
            $baseLineTotal = $qty * ($item->getAttributes()['unit_price'] ?? 0);
            
            // Calculate Tax
            $itemTaxAmount = 0;
            $isInclusive = false;
            
            if ($item->tax_id) {
                $tax = \Tek2991\Accounting\Models\Tax::with('components')->find($item->tax_id);
                if ($tax) {
                    $isInclusive = $tax->type === \Tek2991\Accounting\Enums\TaxType::Inclusive;
                    $taxComponents = app(\Tek2991\Accounting\Services\TaxService::class)->calculateTax($baseLineTotal, $tax, null, 'purchase');
                    $itemTaxAmount = $taxComponents->sum('amount');
                    $item->tax_snapshot = $taxComponents->toArray();
                }
            }
            
            // Determine item's pre-tax line total
            $itemPreTaxTotal = $isInclusive ? ($baseLineTotal - $itemTaxAmount) : $baseLineTotal;
            
            $item->gross_amount = $baseLineTotal / 100;
            $item->line_discount_amount = 0;
            $item->allocated_document_discount = 0;
            $item->net_amount = $baseLineTotal / 100;
            
            $item->line_total = $itemPreTaxTotal / 100;
            $item->tax_amount = $itemTaxAmount / 100;
            
            $item->save();

            $subtotal += $itemPreTaxTotal;
            $taxTotal += $itemTaxAmount;
        }

        $dn->subtotal = $subtotal / 100;
        $dn->tax_total = $taxTotal / 100;
        
        $grandTotal = $subtotal + $taxTotal;
        $dn->grand_total = $grandTotal / 100;
        
        $balanceRemaining = $grandTotal - ($dn->getAttributes()['applied_amount'] ?? 0);
        $dn->balance_remaining = $balanceRemaining / 100;

        $dn->save();
    }

    public function post(DebitNote $dn): void
    {
        DB::transaction(function () use ($dn) {
            $dn = DebitNote::lockForUpdate()->find($dn->id);
            
            if ($dn->transaction_id !== null) {
                return;
            }
            
            if ($dn->status !== DebitNoteStatus::Draft) {
                throw new Exception("Only draft debit notes can be posted.");
            }

            $payableAccountId = $dn->contact->payableAccount?->id ?? \Tek2991\Accounting\Models\Account::where('company_id', $dn->company_id)
                ->where('system_role', \Tek2991\Accounting\Enums\SystemRole::TradePayable)
                ->value('id');

            if (!$payableAccountId) {
                throw new \Exception("Cannot post debit note: No payable account found for vendor and no default Accounts Payable exists.");
            }

            $entries = [];

            // DR: Payable Account
            $entries[] = [
                'account_id' => $payableAccountId,
                'type' => 'debit',
                'amount' => $dn->grand_total,
                'description' => "Debit Note {$dn->debit_note_number}",
            ];

            // CR: Expense Accounts & Taxes (reversal)
            $expenseAccounts = [];
            $taxAccounts = [];

            foreach ($dn->items as $item) {
                $expAccountId = $item->item->expense_account_id ?? null;
                if (!$expAccountId) {
                    throw new Exception("Missing expense account for item.");
                }

                if (!isset($expenseAccounts[$expAccountId])) {
                    $expenseAccounts[$expAccountId] = 0;
                }
                $expenseAccounts[$expAccountId] += $item->line_total;

                if ($item->tax_snapshot) {
                    foreach ($item->tax_snapshot as $taxComp) {
                        $taxAccId = $taxComp['account_id'];
                        if (!isset($taxAccounts[$taxAccId])) {
                            $taxAccounts[$taxAccId] = 0;
                        }
                        $taxAccounts[$taxAccId] += ($taxComp['amount'] / 100);
                    }
                }
            }

            foreach ($expenseAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'credit',
                        'amount' => $amount,
                        'description' => "Debit Note {$dn->debit_note_number} Expense Reversal",
                    ];
                }
            }

            foreach ($taxAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'credit',
                        'amount' => $amount,
                        'description' => "Debit Note {$dn->debit_note_number} Tax Reversal",
                    ];
                }
            }

            $transaction = $this->txnService->createTransaction([
                'company_id' => $dn->company_id,
                'type' => \Tek2991\Accounting\Enums\TransactionType::DebitNote,
                'description' => "Posted Debit Note {$dn->debit_note_number}",
                'posted_at' => $dn->issue_date?->toDateString() ?? now()->toDateString(),
                'reference' => $dn->debit_note_number,
            ], $entries);

            $dn->transaction_id = $transaction->id;
            $dn->status = DebitNoteStatus::Issued;
            $dn->save();
            
            activity('financial')
                ->performedOn($dn)
                ->event('debit_note.posted')
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'grand_total' => $dn->grand_total
                ])
                ->log("Debit Note {$dn->debit_note_number} posted");
        });
    }

    public function cancel(DebitNote $dn): void
    {
        DB::transaction(function () use ($dn) {
            $dn = DebitNote::lockForUpdate()->find($dn->id);
            
            if ($dn->status === DebitNoteStatus::Cancelled) {
                return;
            }
            if ($dn->status === DebitNoteStatus::Applied) {
                throw new Exception("Cannot cancel an applied debit note.");
            }
            
            if ($dn->transaction_id) {
                $this->txnService->reverseTransaction($dn->transaction, "Reversal of Cancelled DN: {$dn->debit_note_number}");
            }

            $dn->status = DebitNoteStatus::Cancelled;
            $dn->save();
            
            activity('financial')
                ->performedOn($dn)
                ->event('debit_note.cancelled')
                ->log("Debit Note {$dn->debit_note_number} cancelled");
        });
    }
}
