<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\Pages;

use Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\CreditNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tek2991\Accounting\Services\CreditNoteService;

class EditCreditNote extends EditRecord
{
    protected static string $resource = CreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function afterSave(): void
    {
        $service = app(CreditNoteService::class);
        $service->recalculateTotals($this->record);
    }
}
