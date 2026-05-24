<?php

namespace Tek2991\Accounting\Filament\Resources\Taxes\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Tek2991\Accounting\Enums\AccountCategory;
use Tek2991\Accounting\Enums\TaxType;

class TaxForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tax Details')
                    ->columns(2)
                    ->components([
                        Forms\Components\TextInput::make('name')
                            ->label('Tax Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. GST 18%'),
                            
                        Forms\Components\Select::make('type')
                            ->label('Calculation Type')
                            ->options(TaxType::class)
                            ->default(TaxType::Exclusive)
                            ->required(),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
                    
                Section::make('Tax Components')
                    ->description('Define the individual breakdown (e.g. CGST 9%, SGST 9%)')
                    ->components([
                        Forms\Components\Repeater::make('components')
                            ->relationship()
                            ->columns(3)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Component Name')
                                    ->required()
                                    ->placeholder('e.g. CGST'),
                                    
                                Forms\Components\TextInput::make('rate')
                                    ->label('Rate (%)')
                                    ->numeric()
                                    ->required()
                                    ->step('0.0001')
                                    ->suffix('%'),
                                    
                                Forms\Components\Select::make('account_id')
                                    ->label('Posting Account')
                                    ->relationship('account', 'name', fn ($query) => $query->where('category', AccountCategory::Liability))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])
                            ->defaultItems(1)
                            ->addActionLabel('Add Component')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                    ]),
            ]);
    }
}
