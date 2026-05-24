<?php

namespace Tek2991\Accounting\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Tek2991\Accounting\Contracts\CompanyAccessor;
use Tek2991\Accounting\Enums\AccountCategory;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\AccountSubtype;
use Tek2991\Accounting\Utilities\AccountCode;
use Illuminate\Support\Collection;

class AccountChart extends Page
{
    protected string $view = 'accounting::filament.pages.account-chart';

    protected static \UnitEnum|string|null $navigationGroup = 'Accounting';

    protected static \BackedEnum|string|null $navigationIcon = null;

    protected static ?string $navigationLabel = 'Chart of Accounts';

    protected static ?int $navigationSort = 2;

    /**
     * The currently active category tab (URL-persisted).
     */
    #[Url]
    public string $activeTab = 'asset';

    // ──────────────────────────────────────────────────────────────
    // Computed data
    // ──────────────────────────────────────────────────────────────

    /**
     * Accounts grouped by category → subtype for the active tab.
     * Returns Collection<string, Collection<AccountSubtype, Account[]>>
     */
    #[Computed]
    public function accountsBySubtype(): Collection
    {
        $category = AccountCategory::from($this->activeTab);

        $subtypes = AccountSubtype::query()
            ->where('category', $category)
            ->withCount('accounts')
            ->with(['accounts' => function ($q) {
                $q->orderBy('code');
            }])
            ->get();

        return $subtypes;
    }

    /**
     * All AccountCategory cases for rendering the tab bar.
     */
    public function getCategories(): array
    {
        return AccountCategory::cases();
    }

    // ──────────────────────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────────────────────

    protected function configureAction(Action $action): void
    {
        $action
            ->slideOver()
            ->modalWidth('2xl');
    }

    /**
     * Create a new account inline (pre-filled with the active category defaults).
     */
    public function createAccountAction(): Action
    {
        return CreateAction::make('createAccount')
            ->label('New Account')
            ->icon('heroicon-o-plus')
            ->model(Account::class)
            ->form(fn (Schema $schema) => $this->buildAccountForm($schema))
            ->fillForm(fn (array $arguments) => $this->getAccountFormDefaults($arguments))
            ->mutateDataUsing(function (array $data) {
                $companyId = app(CompanyAccessor::class)->getCurrentCompanyId();
                $data['company_id'] = $companyId;

                return $data;
            })
            ->successNotificationTitle('Account created successfully');
    }

    /**
     * Edit an existing account (keyed by account ID passed as argument).
     */
    public function editAccountAction(): Action
    {
        return EditAction::make('editAccount')
            ->label('Edit')
            ->icon('heroicon-o-pencil-square')
            ->iconButton()
            ->record(fn (array $arguments) => Account::find($arguments['account'] ?? null))
            ->form(fn (Schema $schema) => $this->buildAccountForm($schema))
            ->successNotificationTitle('Account updated successfully');
    }

    /**
     * Quick-toggle archive status for an account.
     */
    public function toggleArchiveAction(): Action
    {
        return Action::make('toggleArchive')
            ->label(fn (array $arguments) => Account::find($arguments['account'] ?? null)?->archived ? 'Restore' : 'Archive')
            ->icon(fn (array $arguments) => Account::find($arguments['account'] ?? null)?->archived ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-archive-box')
            ->color(fn (array $arguments) => Account::find($arguments['account'] ?? null)?->archived ? 'success' : 'warning')
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                $account = Account::find($arguments['account'] ?? null);
                if ($account) {
                    $account->update(['archived' => ! $account->archived]);
                    Notification::make()
                        ->success()
                        ->title($account->archived ? 'Account archived' : 'Account restored')
                        ->send();
                }
            });
    }

    /**
     * Add a new account scoped to a specific subtype.
     */
    public function createAccountForSubtypeAction(): Action
    {
        return CreateAction::make('createAccountForSubtype')
            ->label('Add account')
            ->link()
            ->icon('heroicon-o-plus-circle')
            ->model(Account::class)
            ->form(fn (Schema $schema) => $this->buildAccountForm($schema))
            ->fillForm(fn (array $arguments) => $this->getAccountFormDefaultsForSubtype($arguments['subtypeId'] ?? null))
            ->mutateDataUsing(function (array $data) {
                $companyId = app(CompanyAccessor::class)->getCurrentCompanyId();
                $data['company_id'] = $companyId;

                return $data;
            })
            ->successNotificationTitle('Account created successfully');
    }

    // ──────────────────────────────────────────────────────────────
    // Form builder
    // ──────────────────────────────────────────────────────────────

    private function buildAccountForm(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('category')
                    ->label('Category')
                    ->options(AccountCategory::class)
                    ->required()
                    ->live()
                    ->disabledOn('edit')
                    ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set) {
                        $set('type', null);
                        $set('subtype_id', null);
                        $set('code', null);
                    }),

                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options(function (\Filament\Schemas\Components\Utilities\Get $get) {
                        $category = $get('category');
                        if (! $category) {
                            return [];
                        }
                        $category = $category instanceof AccountCategory ? $category : AccountCategory::from($category);

                        return collect(AccountType::forCategory($category))
                            ->mapWithKeys(fn (AccountType $t) => [$t->value => $t->getLabel()]);
                    })
                    ->required()
                    ->live()
                    ->disabledOn('edit')
                    ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                        $set('subtype_id', null);
                    }),
            ]),

            \Filament\Schemas\Components\Grid::make(2)->schema([
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
                    ->live()
                    ->nullable()
                    ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get, $state) {
                        // Auto-generate code when subtype is selected
                        if ($state) {
                            $subtype = AccountSubtype::find($state);
                            if ($subtype) {
                                $companyId = app(CompanyAccessor::class)->getCurrentCompanyId();
                                $set('code', AccountCode::generate($subtype, $companyId));
                            }
                        }
                    }),

                Forms\Components\TextInput::make('code')
                    ->label('Account Code')
                    ->required()
                    ->maxLength(10)
                    ->unique(table: Account::class, column: 'code', ignoreRecord: true)
                    ->helperText(function (\Filament\Schemas\Components\Utilities\Get $get) {
                        $category = $get('category');
                        if (! $category) {
                            return 'Select a category first';
                        }
                        $category = $category instanceof AccountCategory ? $category : AccountCategory::from($category);

                        return "Range: {$category->getCodeRangeStart()} – {$category->getCodeRangeEnd()}";
                    }),
            ]),

            Forms\Components\TextInput::make('name')
                ->label('Account Name')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            \Filament\Schemas\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('currency_code')
                    ->label('Currency')
                    ->options(function () {
                        try {
                            $currencies = \Symfony\Component\Intl\Currencies::getNames();
                            $formatted  = [];
                            foreach ($currencies as $code => $name) {
                                $formatted[$code] = "{$name} ({$code})";
                            }

                            return $formatted;
                        } catch (\Throwable) {
                            return ['USD' => 'US Dollar (USD)', 'EUR' => 'Euro (EUR)', 'GBP' => 'British Pound (GBP)'];
                        }
                    })
                    ->searchable()
                    ->default(fn () => \Tek2991\Accounting\Facades\Accounting::getCurrency())
                    ->required(),

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
            ]),

            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->maxLength(1000)
                ->rows(2)
                ->columnSpanFull(),

            \Filament\Schemas\Components\Grid::make(2)->schema([
                Forms\Components\Toggle::make('default')
                    ->label('Default Account')
                    ->helperText('Use as the default for this category in transactions'),

                Forms\Components\Toggle::make('archived')
                    ->label('Archived')
                    ->helperText('Archived accounts are hidden from transaction forms')
                    ->hiddenOn('create'),
            ]),
        ]);
    }

    private function getAccountFormDefaults(array $arguments): array
    {
        $category = AccountCategory::tryFrom($this->activeTab);
        $defaults = [
            'category'      => $this->activeTab,
            'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
        ];

        if ($category) {
            $companyId       = app(CompanyAccessor::class)->getCurrentCompanyId();
            $defaults['code'] = AccountCode::generateForCategory($category, $companyId);
        }

        return $defaults;
    }

    private function getAccountFormDefaultsForSubtype(?int $subtypeId): array
    {
        $defaults = [
            'category'      => $this->activeTab,
            'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
        ];

        if ($subtypeId) {
            $subtype = AccountSubtype::find($subtypeId);
            if ($subtype) {
                $companyId           = app(CompanyAccessor::class)->getCurrentCompanyId();
                $defaults['subtype_id'] = $subtypeId;
                $defaults['type']       = $subtype->type->value;
                $defaults['code']       = AccountCode::generate($subtype, $companyId);
            }
        }

        return $defaults;
    }

    // ──────────────────────────────────────────────────────────────
    // Header actions
    // ──────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            $this->createAccountAction(),
        ];
    }
}
