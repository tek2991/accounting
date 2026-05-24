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
            $lineTotal = $item->getRawOriginal('quantity') * $item->getRawOriginal('unit_price');
            $item->line_total = $lineTotal / 100;
            $item->tax_amount = $item->getRawOriginal('tax_amount') / 100;
            $item->save();

            $subtotal += $lineTotal;
            $taxTotal += $item->getRawOriginal('tax_amount');
        }

        $dn->subtotal = $subtotal / 100;
        $dn->tax_total = $taxTotal / 100;
        
        $grandTotal = $subtotal + $taxTotal;
        $dn->grand_total = $grandTotal / 100;
        
        $balanceRemaining = $grandTotal - $dn->getRawOriginal('applied_amount');
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

            $payableAccountId = $dn->contact->payable_account_id;
            if (!$payableAccountId) {
                throw new Exception("Contact must have a payable account to post debit note.");
            }

            $entries = [];

            // DR: Payable Account
            $entries[] = [
                'account_id' => $payableAccountId,
                'debit' => $dn->getRawOriginal('grand_total'),
                'credit' => 0,
                'description' => "Debit Note {$dn->debit_note_number}",
            ];

            // CR: Expense Accounts & Taxes (reversal)
            $expenseAccounts = [];
            $taxAccounts = [];

            foreach ($dn->items as $item) {
                $expAccountId = $item->item->expense_account_id ?? $dn->bill?->default_expense_account_id;
                if (!$expAccountId) {
                    throw new Exception("Missing expense account for item.");
                }

                if (!isset($expenseAccounts[$expAccountId])) {
                    $expenseAccounts[$expAccountId] = 0;
                }
                $expenseAccounts[$expAccountId] += $item->getRawOriginal('line_total');

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
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => "Debit Note {$dn->debit_note_number} Expense Reversal",
                    ];
                }
            }

            foreach ($taxAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => "Debit Note {$dn->debit_note_number} Tax Reversal",
                    ];
                }
            }

            $transaction = $this->txnService->createTransaction(
                [
                    'company_id' => $dn->company_id,
                    'type' => \Tek2991\Accounting\Enums\TransactionType::DebitNote,
                    'description' => "Posted Debit Note {$dn->debit_note_number}",
                    'posted_at' => $dn->issue_date?->toDateString() ?? now()->toDateString(),
                    'reference' => $dn->debit_note_number,
                ],
                $entries
            );

            $dn->transaction_id = $transaction->id;
            $dn->status = DebitNoteStatus::Issued;
            $dn->save();
            
            activity('financial')
                ->performedOn($dn)
                ->event('debit_note.posted')
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'grand_total' => $dn->getRawOriginal('grand_total')
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
