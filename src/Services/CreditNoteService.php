<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Models\CreditNote;
use Tek2991\Accounting\Enums\CreditNoteStatus;
use Exception;

class CreditNoteService
{
    public function __construct(
        private TransactionService $txnService,
        private DocumentNumberService $docNumberService,
        private PostingGuard $postingGuard,
    ) {}

    public function create(int $companyId, array $data): CreditNote
    {
        return DB::transaction(function () use ($companyId, $data) {
            $cn = new CreditNote($data);
            $cn->company_id = $companyId;
            $cn->credit_note_number = $this->docNumberService->nextCreditNoteNumber($companyId);
            $cn->status = CreditNoteStatus::Draft;
            $cn->save();

            return $cn;
        });
    }

    public function recalculateTotals(CreditNote $cn): void
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($cn->items as $item) {
            $lineTotal = $item->getRawOriginal('quantity') * $item->getRawOriginal('unit_price');
            $item->line_total = $lineTotal / 100;
            $item->tax_amount = $item->getRawOriginal('tax_amount') / 100;
            $item->save();

            $subtotal += $lineTotal;
            $taxTotal += $item->getRawOriginal('tax_amount');
        }

        $cn->subtotal = $subtotal / 100;
        $cn->tax_total = $taxTotal / 100;
        
        $grandTotal = $subtotal + $taxTotal;
        $cn->grand_total = $grandTotal / 100;
        
        $balanceRemaining = $grandTotal - $cn->getRawOriginal('applied_amount');
        $cn->balance_remaining = $balanceRemaining / 100;

        $cn->save();
    }

    public function post(CreditNote $cn): void
    {
        DB::transaction(function () use ($cn) {
            $cn = CreditNote::lockForUpdate()->find($cn->id);
            
            if ($cn->transaction_id !== null) {
                return;
            }
            
            if ($cn->status !== CreditNoteStatus::Draft) {
                throw new Exception("Only draft credit notes can be posted.");
            }

            $receivableAccountId = $cn->contact->receivable_account_id;
            if (!$receivableAccountId) {
                throw new Exception("Contact must have a receivable account to post credit note.");
            }

            $entries = [];

            // CR: Receivable Account
            $entries[] = [
                'account_id' => $receivableAccountId,
                'debit' => 0,
                'credit' => $cn->getRawOriginal('grand_total'),
                'description' => "Credit Note {$cn->credit_note_number}",
            ];

            // DR: Income Accounts & Taxes (reversal)
            $incomeAccounts = [];
            $taxAccounts = [];

            foreach ($cn->items as $item) {
                $incAccountId = $item->item->income_account_id ?? $cn->invoice?->default_income_account_id;
                if (!$incAccountId) {
                    throw new Exception("Missing income account for item.");
                }

                if (!isset($incomeAccounts[$incAccountId])) {
                    $incomeAccounts[$incAccountId] = 0;
                }
                $incomeAccounts[$incAccountId] += $item->getRawOriginal('line_total');

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
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Credit Note {$cn->credit_note_number} Revenue Reversal",
                    ];
                }
            }

            foreach ($taxAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Credit Note {$cn->credit_note_number} Tax Reversal",
                    ];
                }
            }

            $transaction = $this->txnService->createTransaction(
                [
                    'company_id' => $cn->company_id,
                    'type' => \Tek2991\Accounting\Enums\TransactionType::CreditNote,
                    'description' => "Posted Credit Note {$cn->credit_note_number}",
                    'posted_at' => $cn->issue_date?->toDateString() ?? now()->toDateString(),
                    'reference' => $cn->credit_note_number,
                ],
                $entries
            );

            $cn->transaction_id = $transaction->id;
            $cn->status = CreditNoteStatus::Issued;
            $cn->save();
            
            activity('financial')
                ->performedOn($cn)
                ->event('credit_note.posted')
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'grand_total' => $cn->getRawOriginal('grand_total')
                ])
                ->log("Credit Note {$cn->credit_note_number} posted");
        });
    }

    public function cancel(CreditNote $cn): void
    {
        DB::transaction(function () use ($cn) {
            $cn = CreditNote::lockForUpdate()->find($cn->id);
            
            if ($cn->status === CreditNoteStatus::Cancelled) {
                return;
            }
            if ($cn->status === CreditNoteStatus::Applied) {
                throw new Exception("Cannot cancel an applied credit note.");
            }
            
            if ($cn->transaction_id) {
                $this->txnService->reverseTransaction($cn->transaction, "Reversal of Cancelled CN: {$cn->credit_note_number}");
            }

            $cn->status = CreditNoteStatus::Cancelled;
            $cn->save();
            
            activity('financial')
                ->performedOn($cn)
                ->event('credit_note.cancelled')
                ->log("Credit Note {$cn->credit_note_number} cancelled");
        });
    }
}
