<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\Bills\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Tek2991\Accounting\Models\Item;
use Tek2991\Accounting\Models\Tax;
use Tek2991\Accounting\Enums\AccountType;

class BillForm
{
    public static function configure(Schema $schema): Schema
    {
        $getTaxType = function ($taxId) {
            static $cache = [];
            if (!$taxId) return null;
            if (!isset($cache[$taxId])) {
                $cache[$taxId] = \Tek2991\Accounting\Models\Tax::find($taxId)?->type?->value;
            }
            return $cache[$taxId];
        };
        
        $calculateTotals = function ($get) use ($getTaxType) {
            $grossSubtotal = 0;
            $totalItemDiscounts = 0;
            $itemsData = [];
            
            foreach ((array) $get('items') as $index => $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_price'] ?? 0);
                $gross = $qty * $price;
                $grossSubtotal += $gross;
                
                $itemDiscount = 0;
                if (($item['discount_type'] ?? 'percentage') === 'percentage') {
                    $itemDiscount = $gross * ((float) ($item['discount_rate'] ?? 0) / 100);
                } else {
                    $itemDiscount = (float) ($item['discount_amount'] ?? 0);
                }
                $totalItemDiscounts += $itemDiscount;
                
                $lineNetBeforeDoc = $gross - $itemDiscount;
                
                $itemsData[$index] = [
                    'item' => $item,
                    'lineNetBeforeDoc' => $lineNetBeforeDoc,
                ];
            }
            
            $preDocSubtotal = $grossSubtotal - $totalItemDiscounts;
            $docDiscount = 0;
            if (($get('discount_type') ?? 'percentage') === 'percentage') {
                $docDiscount = $preDocSubtotal * ((float) ($get('discount_rate') ?? 0) / 100);
            } else {
                $docDiscount = (float) ($get('discount_amount') ?? 0);
            }
            
            $remainingDocDiscount = $docDiscount;
            $itemsCount = count($itemsData);
            $i = 0;
            
            $taxableAmount = 0;
            $taxTotal = 0;
            $netItemsTotal = 0;
            
            foreach ($itemsData as $index => $data) {
                $i++;
                $item = $data['item'];
                $lineNetBeforeDoc = $data['lineNetBeforeDoc'];
                
                $allocated = 0;
                if ($itemsCount > 0) {
                    if ($i === $itemsCount) {
                        $allocated = $remainingDocDiscount;
                    } else {
                        $proportion = $preDocSubtotal > 0 ? ($lineNetBeforeDoc / $preDocSubtotal) : 0;
                        $allocated = round($docDiscount * $proportion, 2);
                        $remainingDocDiscount -= $allocated;
                    }
                }
                
                $taxableValue = $lineNetBeforeDoc - $allocated;
                
                $itemTaxAmount = 0;
                $isInclusive = false;
                $hasTax = false;
                
                if (!empty($item['tax_id'])) {
                    $hasTax = true;
                    $docMode = $get('tax_computation_mode') ?? 'exclusive';
                    
                    if ($docMode === 'manual') {
                        $isInclusive = false;
                        foreach ($item['tax_snapshot'] ?? [] as $comp) {
                            $itemTaxAmount += (float) ($comp['amount'] ?? 0);
                        }
                    } else {
                        $isInclusive = $docMode === 'inclusive';
                        $rateSum = 0;
                        foreach ($item['tax_snapshot'] ?? [] as $comp) {
                            $rateSum += (float) ($comp['rate'] ?? 0);
                        }
                        if ($isInclusive) {
                            $itemTaxAmount = $taxableValue * ($rateSum / (100 + $rateSum));
                        } else {
                            $itemTaxAmount = $taxableValue * ($rateSum / 100);
                        }
                    }
                }
                
                $itemPreTaxTotal = $isInclusive ? ($taxableValue - $itemTaxAmount) : $taxableValue;
                
                if ($hasTax) {
                    $taxableAmount += $itemPreTaxTotal;
                }
                
                $netItemsTotal += $itemPreTaxTotal;
                $taxTotal += $itemTaxAmount;
            }
            
            $totalDiscount = $totalItemDiscounts + $docDiscount;
            $grandTotal = $netItemsTotal + $taxTotal;
            
            return [
                'subtotal' => $grossSubtotal,
                'total_discount' => $totalDiscount,
                'taxable_amount' => $taxableAmount,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
            ];
        };

        return $schema
            ->components([
                Section::make('Bill Details')
                    ->columnSpan(1)
                    ->columns(2)
                    ->components([
                        Forms\Components\Placeholder::make('status')
                            ->content(fn ($record) => $record?->display_status ? strtoupper($record->display_status) : '')
                            ->hidden(fn ($record) => !$record)
                            ->extraAttributes(['class' => 'font-bold text-lg text-primary-600']),
                            
                        Forms\Components\Select::make('contact_id')
                            ->label('Vendor')
                            ->relationship('contact', 'name', fn ($query) => $query->whereIn('type', [\Tek2991\Accounting\Enums\ContactType::Vendor, \Tek2991\Accounting\Enums\ContactType::Both]))
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
                            ->options(function () {
                                try {
                                    $names = \Symfony\Component\Intl\Currencies::getNames();
                                    $formatted = [];
                                    foreach ($names as $code => $name) {
                                        $formatted[$code] = "{$code} - {$name}";
                                    }
                                    return $formatted;
                                } catch (\Throwable) {
                                    return ['USD' => 'USD - US Dollar', 'INR' => 'INR - Indian Rupee', 'EUR' => 'EUR - Euro', 'GBP' => 'GBP - British Pound'];
                                }
                            })
                            ->default(fn () => \Tek2991\Accounting\Facades\Accounting::getCurrency())
                            ->searchable()
                            ->required(),
                            
                        Forms\Components\Select::make('tax_computation_mode')
                            ->options([
                                'exclusive' => 'Auto - Tax Exclusive',
                                'inclusive' => 'Auto - Tax Inclusive',
                                'manual' => 'Manual Tax (Override)',
                            ])
                            ->default('exclusive')
                            ->required()
                            ->reactive(),
                            
                        Forms\Components\Select::make('discount_type')
                            ->options([
                                \Tek2991\Accounting\Enums\DiscountType::Percentage->value => 'Percentage',
                                \Tek2991\Accounting\Enums\DiscountType::Fixed->value => 'Fixed',
                            ])
                            ->default(\Tek2991\Accounting\Enums\DiscountType::Percentage->value)
                            ->reactive(),
                            
                        Forms\Components\TextInput::make('discount_rate')
                            ->label('Discount %')
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->visible(fn ($get) => $get('discount_type') === \Tek2991\Accounting\Enums\DiscountType::Percentage->value)
                            ->helperText(function ($get) use ($calculateTotals) {
                                $netItemsTotal = $calculateTotals($get)['subtotal'];
                                $rate = (float) ($get('discount_rate') ?? 0);
                                $amount = $netItemsTotal * ($rate / 100);
                                $currency = $get('currency_code') ?? 'USD';
                                return "Amount: {$currency} " . number_format($amount, 2);
                            }),
                            
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount Amount')
                            ->numeric()
                            ->default(0)
                            ->reactive()
                            ->visible(fn ($get) => $get('discount_type') === \Tek2991\Accounting\Enums\DiscountType::Fixed->value)
                            ->helperText(function ($get) use ($calculateTotals) {
                                $netItemsTotal = $calculateTotals($get)['subtotal'];
                                $amount = (float) ($get('discount_amount') ?? 0);
                                $pct = $netItemsTotal > 0 ? ($amount / $netItemsTotal) * 100 : 0;
                                return "Rate: " . number_format($pct, 2) . '%';
                            }),


                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),
                    
                Section::make('Summary')
                    ->columnSpan(1)
                    ->columns(2)
                    ->components([
                        Forms\Components\Placeholder::make('subtotal')
                            ->label('Subtotal')
                            ->content(fn ($get) => ($get('currency_code') ?? 'USD') . ' ' . number_format($calculateTotals($get)['subtotal'], 2)),
                        Forms\Components\Placeholder::make('total_discount')
                            ->label('Total Discount')
                            ->content(fn ($get) => ($get('currency_code') ?? 'USD') . ' ' . number_format($calculateTotals($get)['total_discount'], 2)),
                        Forms\Components\Placeholder::make('taxable_amount')
                            ->label('Taxable Amount')
                            ->content(fn ($get) => ($get('currency_code') ?? 'USD') . ' ' . number_format($calculateTotals($get)['taxable_amount'], 2)),
                        Forms\Components\Placeholder::make('tax_total')
                            ->label('Total Tax')
                            ->content(fn ($get) => ($get('currency_code') ?? 'USD') . ' ' . number_format($calculateTotals($get)['tax_total'], 2)),
                        Forms\Components\Placeholder::make('grand_total')
                            ->label('Grand Total')
                            ->content(fn ($get) => ($get('currency_code') ?? 'USD') . ' ' . number_format($calculateTotals($get)['grand_total'], 2))
                            ->extraAttributes(['class' => 'font-bold text-lg']),
                        Forms\Components\Placeholder::make('balance_due')
                            ->label('Balance Due')
                            ->content(function ($get, $record) use ($calculateTotals) {
                                $grandTotal = $calculateTotals($get)['grand_total'];
                                $paid = $record ? (float) $record->amount_paid : 0;
                                return ($get('currency_code') ?? 'USD') . ' ' . number_format(max(0, $grandTotal - $paid), 2);
                            })
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
                                Forms\Components\ToggleButtons::make('line_type')
                                    ->options([
                                        'item' => 'Item',
                                        'account' => 'Account',
                                    ])
                                    ->default('item')
                                    ->inline()
                                    ->reactive()
                                    ->required()
                                    ->columnSpanFull()
                                    ->afterStateUpdated(function (callable $set) {
                                        $set('item_id', null);
                                        $set('expense_account_id', null);
                                        $set('description', null);
                                        $set('quantity', 1);
                                        $set('unit_price', null);
                                    }),
                                    
                                Forms\Components\Select::make('item_id')
                                    ->relationship('item', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3)
                                    ->reactive()
                                    ->visible(fn ($get) => $get('line_type') === 'item')
                                    ->required(fn ($get) => $get('line_type') === 'item')
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
                                    
                                Forms\Components\Select::make('expense_account_id')
                                    ->relationship('expenseAccount', 'name', fn ($query) => $query->where('type', \Tek2991\Accounting\Enums\AccountType::Expense))
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3)
                                    ->reactive()
                                    ->visible(fn ($get) => $get('line_type') === 'account')
                                    ->required(fn ($get) => $get('line_type') === 'account'),
                                    
                                Forms\Components\TextInput::make('description')
                                    ->required()
                                    ->columnSpan(3),
                                    
                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->reactive()
                                    ->columnSpan(1)
                                    ->visible(fn ($get) => $get('line_type') === 'item'),
                                    
                                Forms\Components\TextInput::make('unit_price')
                                    ->label(fn ($get) => $get('line_type') === 'account' ? 'Amount' : 'Unit Price')
                                    ->numeric()
                                    ->required()
                                    ->reactive()
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
                                                    'amount' => 0, // minor units
                                                ])->toArray();
                                                $set('tax_snapshot', $components);
                                            }
                                        }
                                    }),
                                    
                                Forms\Components\Repeater::make('tax_snapshot')
                                    ->schema([
                                        Forms\Components\Hidden::make('account_id'),
                                        Forms\Components\Hidden::make('rate'),
                                        Forms\Components\TextInput::make('name')
                                            ->disabled()
                                            ->dehydrated(true)
                                            ->columnSpan(2),
                                        Forms\Components\TextInput::make('amount')
                                            ->numeric()
                                            ->required()
                                            ->reactive()
                                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                                            ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state / 100, 2, '.', '') : '0.00')
                                            ->columnSpan(2),
                                    ])
                                    ->columns(4)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->visible(fn ($get) => $get('../../tax_computation_mode') === 'manual')
                                    ->columnSpanFull()
                                    ->dehydrated(fn ($get) => $get('../../tax_computation_mode') === 'manual'),
                                    
                                Forms\Components\Hidden::make('tax_snapshot_hidden')
                                    ->dehydrated(false),
                                    
                                Forms\Components\Placeholder::make('line_total')
                                    ->content(function ($get) use ($getTaxType) {
                                        $qty = (float) ($get('quantity') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $baseLineTotal = $qty * $price;
                                        
                                        $itemDiscount = 0;
                                        if (($get('discount_type') ?? 'percentage') === 'percentage') {
                                            $itemDiscount = $baseLineTotal * ((float) ($get('discount_rate') ?? 0) / 100);
                                        } else {
                                            $itemDiscount = (float) ($get('discount_amount') ?? 0);
                                        }
                                        
                                        $discountedLineTotal = $baseLineTotal - $itemDiscount;
                                        
                                        $itemTaxAmount = 0;
                                        $isInclusive = false;
                                        if (!empty($get('tax_id'))) {
                                            $docMode = $get('../../tax_computation_mode') ?? 'exclusive';
                                            
                                            if ($docMode === 'manual') {
                                                // Pre-tax is just discounted line total
                                            } else {
                                                $isInclusive = $docMode === 'inclusive';
                                                $rateSum = 0;
                                                foreach ($get('tax_snapshot') ?? [] as $comp) {
                                                    $rateSum += (float) ($comp['rate'] ?? 0);
                                                }
                                                if ($isInclusive) {
                                                    $itemTaxAmount = $discountedLineTotal * ($rateSum / (100 + $rateSum));
                                                }
                                            }
                                        }
                                        
                                        $itemPreTaxTotal = $isInclusive ? ($discountedLineTotal - $itemTaxAmount) : $discountedLineTotal;
                                        return number_format($itemPreTaxTotal, 2);
                                    })
                                    ->columnSpan(1),
                                    
                                Forms\Components\Select::make('discount_type')
                                    ->options([
                                        \Tek2991\Accounting\Enums\DiscountType::Percentage->value => 'Percentage',
                                        \Tek2991\Accounting\Enums\DiscountType::Fixed->value => 'Fixed',
                                    ])
                                    ->default(\Tek2991\Accounting\Enums\DiscountType::Percentage->value)
                                    ->reactive()
                                    ->columnSpan(2),
                                    
                                Forms\Components\TextInput::make('discount_rate')
                                    ->label('Discount %')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->columnSpan(2)
                                    ->visible(fn ($get) => $get('discount_type') === \Tek2991\Accounting\Enums\DiscountType::Percentage->value)
                                    ->helperText(function ($get) {
                                        $qty = (float) ($get('quantity') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $baseTotal = $qty * $price;
                                        $amount = $baseTotal * ((float) ($get('discount_rate') ?? 0) / 100);
                                        return '=' . number_format($amount, 2);
                                    }),
                                    
                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('Discount Amount')
                                    ->numeric()
                                    ->default(0)
                                    ->reactive()
                                    ->columnSpan(2)
                                    ->visible(fn ($get) => $get('discount_type') === \Tek2991\Accounting\Enums\DiscountType::Fixed->value)
                                    ->helperText(function ($get) {
                                        $qty = (float) ($get('quantity') ?? 0);
                                        $price = (float) ($get('unit_price') ?? 0);
                                        $baseTotal = $qty * $price;
                                        $amount = (float) ($get('discount_amount') ?? 0);
                                        $pct = $baseTotal > 0 ? ($amount / $baseTotal) * 100 : 0;
                                        return '=' . number_format($pct, 2) . '%';
                                    }),

                            ])
                            ->defaultItems(1)
                            ->orderColumn('sort_order')
                            ->addActionLabel('Add Line Item')
                    ]),
            ]);
    }
}
