<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\Bills\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Tek2991\Accounting\Models\Item;
use Tek2991\Accounting\Models\Tax;
use Tek2991\Accounting\Enums\AccountCategory;

class BillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bill Details')
                    ->columnSpan(1)
                    ->columns(2)
                    ->components([
                        Forms\Components\Select::make('contact_id')
                            ->label('Vendor')
                            ->relationship('contact', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                            
                        Forms\Components\TextInput::make('vendor_reference')
                            ->label('Vendor Invoice #'),
                            
                        Forms\Components\DatePicker::make('issue_date')
                            ->default(now())
                            ->required(),
                            
                        Forms\Components\DatePicker::make('due_date'),
                        
                        Forms\Components\Select::make('currency_code')
                            ->options(['USD' => 'USD', 'INR' => 'INR'])
                            ->default('USD')
                            ->required(),
                            
                        Forms\Components\Select::make('default_expense_account_id')
                            ->relationship('defaultExpenseAccount', 'name', fn ($query) => $query->where('category', AccountCategory::Expense))
                            ->searchable()
                            ->preload()
                            ->required(),
                            
                        Forms\Components\Textarea::make('notes')
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
                                                $set('unit_price', $item->purchase_price);
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
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $tax = Tax::find($state);
                                            if ($tax) {
                                                $components = $tax->components->map(fn($c) => [
                                                    'account_id' => $c->account_id,
                                                    'name' => $c->name,
                                                    'rate' => $c->rate,
                                                    'amount' => 0,
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
