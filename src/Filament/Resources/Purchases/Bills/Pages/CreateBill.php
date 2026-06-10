<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\Bills\Pages;

use Tek2991\Accounting\Filament\Resources\Purchases\Bills\BillResource;
use Filament\Resources\Pages\CreateRecord;
use Tek2991\Accounting\Services\BillService;

class CreateBill extends CreateRecord
{
    protected static string $resource = BillResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = auth()->user()->company_id ?? 1; // Simplification for demo
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(BillService::class);
        $companyId = $data['company_id'];
        
        $bill = $service->create($companyId, $data);
        return $bill;
    }
    
    protected function afterCreate(): void
    {
        $service = app(BillService::class);
        $this->record->load('items');
        $service->recalculateTotals($this->record);
    }
}
