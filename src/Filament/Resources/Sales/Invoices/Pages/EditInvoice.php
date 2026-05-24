<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\Invoices\Pages;

use Tek2991\Accounting\Filament\Resources\Sales\Invoices\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tek2991\Accounting\Services\InvoiceService;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function afterSave(): void
    {
        $service = app(InvoiceService::class);
        $service->recalculateTotals($this->record);
    }
}
