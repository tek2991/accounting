<?php

namespace Tek2991\Accounting;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tek2991\Accounting\Contracts\CompanyAccessor;
use Tek2991\Accounting\Services\AccountService;
use Tek2991\Accounting\Services\TransactionService;
use Tek2991\Accounting\Services\TaxService;
use Tek2991\Accounting\Services\InvoiceService;
use Tek2991\Accounting\Services\BillService;
use Tek2991\Accounting\Services\FiscalPeriodService;
use Tek2991\Accounting\Services\CreditNoteService;
use Tek2991\Accounting\Services\DebitNoteService;
use Tek2991\Accounting\Services\DocumentNumberService;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

class AccountingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('accounting')
            ->hasConfigFile()
            ->hasMigrations([
                'create_accounting_tables',
                'create_accounting_settings_table',
                'create_bank_accounts_table',
                'add_bank_account_to_transactions_table',
                'add_voucherable_to_acc_transactions_table',
                'add_document_settings_to_acc_settings_table',
                'create_items_table',
                'create_contacts_table',
                'create_taxes_table',
                'add_invoice_numbers_to_settings_table',
                'create_invoices_table',
                'create_invoice_items_table',
                'create_bills_table',
                'create_bill_items_table',
                'create_payments_table',
                'create_fiscal_periods_table',
                'add_cn_dn_settings_to_acc_settings_table',
                'create_credit_notes_table',
                'create_credit_note_items_table',
                'create_debit_notes_table',
                'create_debit_note_items_table',
            ])
            ->hasViews('accounting')
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        FilamentAsset::register([
            Css::make('accounting-styles', __DIR__ . '/../resources/dist/accounting.css'),
        ], 'tek2991/accounting');

        $this->app->singleton('accounting', function ($app) {
            return new \Tek2991\Accounting\AccountingManager();
        });

        $this->app->singleton(CompanyAccessor::class, function ($app) {
            return new class implements CompanyAccessor {
                public function getCurrentCompanyId(): ?int
                {
                    $user = auth()->user();

                    if ($user === null) {
                        return null;
                    }

                    // Filament 4 tenant resolution
                    if (class_exists(\Filament\Facades\Filament::class)) {
                        $tenant = \Filament\Facades\Filament::getTenant();
                        if ($tenant !== null) {
                            return $tenant->getKey();
                        }
                    }

                    // Fallback: check for current_company_id on user
                    if (method_exists($user, 'currentCompany')) {
                        return $user->currentCompany?->getKey();
                    }

                    return null;
                }

                public function getCurrentCompany(): ?\Illuminate\Database\Eloquent\Model
                {
                    if (class_exists(\Filament\Facades\Filament::class)) {
                        return \Filament\Facades\Filament::getTenant();
                    }

                    $user = auth()->user();

                    if ($user !== null && method_exists($user, 'currentCompany')) {
                        return $user->currentCompany;
                    }

                    return null;
                }
            };
        });

        $this->app->singleton(AccountService::class);
        $this->app->singleton(TransactionService::class);
        $this->app->singleton(TaxService::class);
        $this->app->singleton(DocumentNumberService::class);
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(BillService::class);
        $this->app->singleton(FiscalPeriodService::class);
        $this->app->singleton(CreditNoteService::class);
        $this->app->singleton(DebitNoteService::class);
    }

    public function packageBooted(): void
    {
        //
    }
}
