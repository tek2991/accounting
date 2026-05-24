<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Item;
use Tek2991\Accounting\Models\Tax;

class CreditNoteForm
{
    public static function make(): array
    {
        return [
            Section::make('Credit Note Details')
                ->columnSpan(1)
                ->schema([
                    Select::make('contact_id')
                        ->label('Customer')
                        ->options(Contact::where('is_customer', true)->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn (Set $set) => $set('invoice_id', null)),
                        
                    Select::make('invoice_id')
                        ->label('Invoice (Optional)')
                        ->options(fn (Get $get) => Invoice::where('contact_id', $get('contact_id'))->pluck('invoice_number', 'id'))
                        ->searchable()
                        ->reactive(),

                    DatePicker::make('issue_date')
                        ->default(now())
                        ->required(),
                        
                    TextInput::make('reason')
                        ->maxLength(255),
                ]),

            Section::make('Summary')
                ->columnSpan(1)
                ->schema([
                    Placeholder::make('subtotal_placeholder')
                        ->label('Subtotal')
                        ->content(fn ($record) => $record ? number_format($record->subtotal, 2) : '0.00'),

                    Placeholder::make('tax_total_placeholder')
                        ->label('Total Tax')
                        ->content(fn ($record) => $record ? number_format($record->tax_total, 2) : '0.00'),

                    Placeholder::make('grand_total_placeholder')
                        ->label('Grand Total')
                        ->content(fn ($record) => $record ? number_format($record->grand_total, 2) : '0.00')
                        ->extraAttributes(['class' => 'text-xl font-bold']),
                ]),

            Section::make('Line Items')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->schema([
                            Select::make('item_id')
                                ->options(Item::pluck('name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($item = Item::find($state)) {
                                        $set('description', $item->description);
                                        $set('unit_price', $item->unit_price);
                                        $set('tax_id', $item->sales_tax_id);
                                    }
                                }),
                                
                            TextInput::make('description')
                                ->required()
                                ->maxLength(500),
                                
                            TextInput::make('quantity')
                                ->numeric()
                                ->default(1)
                                ->required(),
                                
                            TextInput::make('unit_price')
                                ->numeric()
                                ->required(),
                                
                            Select::make('tax_id')
                                ->label('Tax')
                                ->options(Tax::pluck('name', 'id'))
                                ->searchable(),
                        ])
                        ->columns(5)
                        ->orderColumn('sort_order')
                        ->defaultItems(1)
                ])
        ];
    }
}
