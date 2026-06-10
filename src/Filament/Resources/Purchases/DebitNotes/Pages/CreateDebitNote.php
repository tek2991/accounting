<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\Pages;

use Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\DebitNoteResource;
use Filament\Resources\Pages\CreateRecord;
use Tek2991\Accounting\Services\DebitNoteService;

class CreateDebitNote extends CreateRecord
{
    protected static string $resource = DebitNoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = auth()->user()->company_id ?? 1;
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(DebitNoteService::class);
        return $service->create($data['company_id'], $data);
    }
    
    protected function afterCreate(): void
    {
        $service = app(DebitNoteService::class);
        $this->record->load('items');
        $service->recalculateTotals($this->record);
    }
}
