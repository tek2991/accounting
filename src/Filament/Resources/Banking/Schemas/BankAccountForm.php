<?php

namespace Tek2991\Accounting\Filament\Resources\Banking\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Tek2991\Accounting\Enums\BankAccountType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\AccountSubtype;
use Tek2991\Accounting\Models\BankAccount;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Section::make('Account Information')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Account Type')
                        ->options(BankAccountType::class)
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, $state) {
                            $set('account_id', null);
                            $set('subtype_id_helper', null);
                        })
                        ->columnSpanFull(),

                    Forms\Components\Select::make('subtype_id_helper')
                        ->label('Account Subtype')
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $typeValue = $get('type');
                            if (! $typeValue) {
                                return [];
                            }
                            $bankType = $typeValue instanceof BankAccountType ? $typeValue : BankAccountType::from($typeValue);
                            $category = $bankType->getAccountCategory();
                            $validAccountTypes = $bankType->getValidAccountTypes();

                            return AccountSubtype::query()
                                ->where('category', $category)
                                ->whereIn('type', $validAccountTypes)
                                ->get()
                                ->mapWithKeys(fn (AccountSubtype $s) => [$s->id => $s->name]);
                        })
                        ->live()
                        ->searchable()
                        ->required()
                        ->disabledOn('edit')
                        ->dehydrated(false) // virtual field — drives account_id select
                        ->afterStateHydrated(function (\Filament\Schemas\Components\Component $component, ?BankAccount $record) {
                            if ($record && $record->account) {
                                $component->state($record->account->subtype_id);
                            }
                        })
                        ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set) => $set('account_id', null))
                        ->columnSpanFull(),

                    Forms\Components\Select::make('account_id')
                        ->label('Link to Chart Account')
                        ->helperText('Select the Asset or Liability account from your chart that this bank account represents. A new chart account will need to be created first if one doesn\'t exist.')
                        ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                            $subtypeId = $get('subtype_id_helper');
                            $typeValue = $get('type');
                            if (! $typeValue) {
                                return [];
                            }

                            $query = Account::query()->active()->isNotBankAccount();

                            if ($subtypeId) {
                                $query->where('subtype_id', $subtypeId);
                            } else {
                                $bankType = $typeValue instanceof BankAccountType ? $typeValue : BankAccountType::from($typeValue);
                                $query->whereIn('type', $bankType->getValidAccountTypes());
                            }

                            return $query->pluck('name', 'id');
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => Account::find($value)?->name)
                        ->searchable()
                        ->required()
                        ->disabledOn('edit')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('number')
                        ->label('Account Number')
                        ->helperText('Last 4 digits will be shown as a mask (e.g. •••• 1234).')
                        ->maxLength(30)
                        ->nullable(),

                    Forms\Components\Toggle::make('enabled')
                        ->label('Set as Default')
                        ->helperText('Mark this as the default bank account for this type.')
                        ->default(false),
                ]),
        ]);
    }
}
