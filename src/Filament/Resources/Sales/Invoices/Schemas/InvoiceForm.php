<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\Invoices\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Tek2991\Accounting\Models\Item;
use Tek2991\Accounting\Models\Tax;
use Tek2991\Accounting\Enums\AccountCategory;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Details')
                    ->columnSpan(1)
                    ->columns(2)
                    ->components([
                        Forms\Components\Select::make('contact_id')
                            ->relationship('contact', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                            
                        Forms\Components\DatePicker::make('issue_date')
                            ->default(now())
                            ->required(),
                            
                        Forms\Components\DatePicker::make('due_date'),
                        
                        Forms\Components\Select::make('currency_code')
                            ->options(['USD' => 'USD', 'INR' => 'INR']) // Default options, will enhance later
                            ->default('USD')
                            ->required(),
                            
                        Forms\Components\Select::make('default_income_account_id')
                            ->relationship('defaultIncomeAccount', 'name', fn ($query) => $query->where('category', AccountCategory::Revenue))
                            ->searchable()
                            ->preload()
                            ->required(),
                            
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                            
                        Forms\Components\Textarea::make('terms')
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Summary')
                    ->columnSpan(1)
                    ->columns(2)
                    ->components([
                        Forms\Components\Placeholder::make('subtotal')
                            ->content(fn ($record) => $record ? number_format($record->subtotal, 2) : '0.00'),
                        Forms\Components\Placeholder::make('discount_amount')
                            ->content(fn ($record) => $record ? number_format($record->discount_amount, 2) : '0.00'),
                        Forms\Components\Placeholder::make('tax_total')
                            ->content(fn ($record) => $record ? number_format($record->tax_total, 2) : '0.00'),
                        Forms\Components\Placeholder::make('grand_total')
                            ->content(fn ($record) => $record ? number_format($record->grand_total, 2) : '0.00')
                            ->extraAttributes(['class' => 'font-bold text-lg']),
                        Forms\Components\Placeholder::make('balance_due')
                            ->content(fn ($record) => $record ? number_format($record->balance_due, 2) : '0.00')
                            ->extraAttributes(['class' => 'font-bold text-lg text-danger-600'])
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Line Items')
                    ->columnSpanFull()
                    ->components([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->columns(12)
                            ->schema([
                                Forms\Components\Select::make('item_id')
                                    ->relationship('item', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $item = Item::find($state);
                                            if ($item) {
                                                $set('description', $item->name);
                                                $set('unit_price', $item->sale_price);
                                                $set('hsn_sac_code', $item->hsn_sac_code);
                                            }
                                        }
                                    }),
                                    
                                Forms\Components\TextInput::make('description')
                                    ->required()
                                    ->columnSpan(3),
                                    
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(1),
                                    
                                Forms\Components\TextInput::make('unit_price')
                                    ->numeric()
                                    ->required()
                                    ->columnSpan(2),
                                    
                                Forms\Components\Select::make('tax_id')
                                    ->relationship('tax', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(2)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, $get) {
                                        if ($state) {
                                            $tax = Tax::find($state);
                                            if ($tax) {
                                                // We create a snapshot here for UI purposes, backend service will do actual snapshotting
                                                $components = $tax->components->map(fn($c) => [
                                                    'account_id' => $c->account_id,
                                                    'name' => $c->name,
                                                    'rate' => $c->rate,
                                                    'amount' => 0, // Computed by service
                                                ])->toArray();
                                                $set('tax_snapshot', $components);
                                            }
                                        }
                                    }),
                                    
                                Forms\Components\Hidden::make('tax_snapshot'),
                                    
                                Forms\Components\Placeholder::make('line_total')
                                    ->content(function ($get) {
                                        $qty = (float) $get('quantity') ?: 0;
                                        $price = (float) $get('unit_price') ?: 0;
                                        return number_format($qty * $price, 2);
                                    })
                                    ->columnSpan(1),
                            ])
                            ->defaultItems(1)
                            ->orderColumn('sort_order')
                            ->addActionLabel('Add Line Item')
                    ]),
            ]);
    }
}
