<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\Invoices\Pages;

use Tek2991\Accounting\Filament\Resources\Sales\Invoices\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Services\InvoiceService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Enums\AccountCategory;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => $record->status === InvoiceStatus::Draft),
            Actions\Action::make('post')
                ->label('Post')
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === InvoiceStatus::Draft)
                ->action(function ($record) {
                    app(InvoiceService::class)->post($record);
                    \Filament\Notifications\Notification::make()->title('Invoice posted')->success()->send();
                }),
            Actions\Action::make('record_payment')
                ->label('Record Payment')
                ->icon('heroicon-o-banknotes')
                ->visible(fn ($record) => in_array($record->status, [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid]))
                ->form([
                    TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->default(fn ($record) => $record->balance_due),
                    Select::make('payment_account_id')
                        ->label('Payment Account')
                        ->options(Account::where('type', \Tek2991\Accounting\Enums\AccountType::CurrentAsset)->pluck('name', 'id'))
                        ->required(),
                    DatePicker::make('payment_date')
                        ->default(now())
                        ->required(),
                    TextInput::make('reference'),
                ])
                ->action(function ($record, array $data) {
                    $data['amount'] = (int) round($data['amount'] * 100); // Minor units
                    app(InvoiceService::class)->recordPayment($record, $data);
                    \Filament\Notifications\Notification::make()->title('Payment recorded')->success()->send();
                }),
            Actions\Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->color('danger')
                ->visible(fn ($record) => $record->status !== InvoiceStatus::Cancelled && $record->status !== InvoiceStatus::Paid)
                ->action(function ($record) {
                    app(InvoiceService::class)->cancel($record);
                    \Filament\Notifications\Notification::make()->title('Invoice cancelled')->success()->send();
                }),
        ];
    }
}
