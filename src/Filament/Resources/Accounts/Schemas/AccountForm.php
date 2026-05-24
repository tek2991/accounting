<?php

namespace Tek2991\Accounting\Filament\Resources\Accounts\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Tek2991\Accounting\Enums\AccountCategory;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\AccountSubtype;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Details')
                    ->columns(2)
                    ->components([
                        Forms\Components\Select::make('category')
                            ->options(AccountCategory::class)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set) {
                                $set('type', null);
                                $set('subtype_id', null);
                            }),

                        Forms\Components\Select::make('type')
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $category = $get('category');
                                if (! $category) {
                                    return [];
                                }
                                $category = $category instanceof AccountCategory
                                    ? $category
                                    : AccountCategory::from($category);

                                return collect(AccountType::forCategory($category))
                                    ->mapWithKeys(fn (AccountType $type) => [$type->value => $type->getLabel()]);
                            })
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set) => $set('subtype_id', null)),

                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule, \Filament\Schemas\Components\Utilities\Get $get) {
                                return $rule;
                            })
                            ->helperText(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $category = $get('category');
                                if (! $category) {
                                    return 'Select a category first';
                                }
                                $category = $category instanceof AccountCategory
                                    ? $category
                                    : AccountCategory::from($category);

                                $start = $category->getCodeRangeStart();
                                $end = $category->getCodeRangeEnd();

                                return "Recommended range: {$start}–{$end}";
                            }),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('subtype_id')
                            ->label('Subtype')
                            ->relationship('subtype', 'name')
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                $type = $get('type');
                                if (! $type) {
                                    return [];
                                }

                                return AccountSubtype::where('type', $type)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Account')
                            ->relationship('parent', 'name')
                            ->options(function (\Filament\Schemas\Components\Utilities\Get $get, ?Account $record) {
                                $category = $get('category');
                                if (! $category) {
                                    return [];
                                }

                                $query = Account::where('category', $category);

                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->nullable(),

                        Forms\Components\TextInput::make('currency_code')
                            ->label('Currency')
                            ->default(fn () => \Tek2991\Accounting\Facades\Accounting::getCurrency())
                            ->required()
                            ->maxLength(3),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Status')
                    ->columns(2)
                    ->components([
                        Forms\Components\Toggle::make('archived')
                            ->label('Archived')
                            ->default(false),

                        Forms\Components\Toggle::make('default')
                            ->label('Default Account')
                            ->default(false)
                            ->helperText('Mark as the default account for this category'),
                    ]),
            ]);
    }
}
