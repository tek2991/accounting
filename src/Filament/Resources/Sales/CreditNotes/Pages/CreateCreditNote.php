<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\Pages;

use Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\CreditNoteResource;
use Filament\Resources\Pages\CreateRecord;
use Tek2991\Accounting\Services\CreditNoteService;

class CreateCreditNote extends CreateRecord
{
    protected static string $resource = CreditNoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = auth()->user()->company_id ?? 1;
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(CreditNoteService::class);
        return $service->create($data['company_id'], $data);
    }
    
    protected function afterCreate(): void
    {
        $service = app(CreditNoteService::class);
        $service->recalculateTotals($this->record);
    }
}
