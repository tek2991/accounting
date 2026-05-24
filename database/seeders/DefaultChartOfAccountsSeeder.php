<?php

namespace Tek2991\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Tek2991\Accounting\Enums\AccountCategory;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\AccountSubtype;

class DefaultChartOfAccountsSeeder extends Seeder
{
    /**
     * Seed a standard Chart of Accounts for a given company.
     */
    public function run(int $companyId, string $currencyCode = 'USD'): void
    {
        $this->seedSubtypes($companyId);
        $this->seedAccounts($companyId, $currencyCode);
    }

    protected function seedSubtypes(int $companyId): void
    {
        $subtypes = [
            // Assets
            ['category' => AccountCategory::Asset, 'type' => AccountType::CurrentAsset, 'name' => 'Cash and Cash Equivalents'],
            ['category' => AccountCategory::Asset, 'type' => AccountType::CurrentAsset, 'name' => 'Accounts Receivable'],
            ['category' => AccountCategory::Asset, 'type' => AccountType::CurrentAsset, 'name' => 'Inventory'],
            ['category' => AccountCategory::Asset, 'type' => AccountType::CurrentAsset, 'name' => 'Prepaid Expenses'],
            ['category' => AccountCategory::Asset, 'type' => AccountType::NonCurrentAsset, 'name' => 'Property, Plant & Equipment'],
            ['category' => AccountCategory::Asset, 'type' => AccountType::NonCurrentAsset, 'name' => 'Intangible Assets'],
            ['category' => AccountCategory::Asset, 'type' => AccountType::ContraAsset, 'name' => 'Accumulated Depreciation'],

            // Liabilities
            ['category' => AccountCategory::Liability, 'type' => AccountType::CurrentLiability, 'name' => 'Accounts Payable'],
            ['category' => AccountCategory::Liability, 'type' => AccountType::CurrentLiability, 'name' => 'Accrued Liabilities'],
            ['category' => AccountCategory::Liability, 'type' => AccountType::CurrentLiability, 'name' => 'Taxes Payable'],
            ['category' => AccountCategory::Liability, 'type' => AccountType::CurrentLiability, 'name' => 'Unearned Revenue'],
            ['category' => AccountCategory::Liability, 'type' => AccountType::NonCurrentLiability, 'name' => 'Long-Term Debt'],
            ['category' => AccountCategory::Liability, 'type' => AccountType::NonCurrentLiability, 'name' => 'Mortgage Payable'],

            // Equity
            ['category' => AccountCategory::Equity, 'type' => AccountType::Equity, 'name' => 'Owner\'s Capital'],
            ['category' => AccountCategory::Equity, 'type' => AccountType::Equity, 'name' => 'Retained Earnings'],
            ['category' => AccountCategory::Equity, 'type' => AccountType::ContraEquity, 'name' => 'Owner\'s Drawings'],

            // Revenue
            ['category' => AccountCategory::Revenue, 'type' => AccountType::OperatingRevenue, 'name' => 'Sales Revenue'],
            ['category' => AccountCategory::Revenue, 'type' => AccountType::OperatingRevenue, 'name' => 'Service Revenue'],
            ['category' => AccountCategory::Revenue, 'type' => AccountType::NonOperatingRevenue, 'name' => 'Interest Income'],
            ['category' => AccountCategory::Revenue, 'type' => AccountType::NonOperatingRevenue, 'name' => 'Other Income'],
            ['category' => AccountCategory::Revenue, 'type' => AccountType::ContraRevenue, 'name' => 'Sales Returns & Allowances'],

            // Expenses
            ['category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'name' => 'Cost of Goods Sold'],
            ['category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'name' => 'Payroll & Wages'],
            ['category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'name' => 'Rent & Lease'],
            ['category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'name' => 'Utilities'],
            ['category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'name' => 'Office Supplies'],
            ['category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'name' => 'Insurance'],
            ['category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'name' => 'Depreciation'],
            ['category' => AccountCategory::Expense, 'type' => AccountType::NonOperatingExpense, 'name' => 'Interest Expense'],
            ['category' => AccountCategory::Expense, 'type' => AccountType::NonOperatingExpense, 'name' => 'Bank Fees'],
        ];

        foreach ($subtypes as $subtype) {
            AccountSubtype::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'type' => $subtype['type'],
                    'name' => $subtype['name'],
                ],
                [
                    'category' => $subtype['category'],
                ]
            );
        }
    }

    protected function seedAccounts(int $companyId, string $currencyCode): void
    {
        $accounts = [
            // ── Assets ──────────────────────────────────────────────
            ['code' => '1000', 'name' => 'Cash', 'category' => AccountCategory::Asset, 'type' => AccountType::CurrentAsset, 'subtype' => 'Cash and Cash Equivalents', 'default' => true],
            ['code' => '1010', 'name' => 'Petty Cash', 'category' => AccountCategory::Asset, 'type' => AccountType::CurrentAsset, 'subtype' => 'Cash and Cash Equivalents'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'category' => AccountCategory::Asset, 'type' => AccountType::CurrentAsset, 'subtype' => 'Accounts Receivable', 'default' => true],
            ['code' => '1200', 'name' => 'Inventory', 'category' => AccountCategory::Asset, 'type' => AccountType::CurrentAsset, 'subtype' => 'Inventory'],
            ['code' => '1300', 'name' => 'Prepaid Expenses', 'category' => AccountCategory::Asset, 'type' => AccountType::CurrentAsset, 'subtype' => 'Prepaid Expenses'],
            ['code' => '1500', 'name' => 'Equipment', 'category' => AccountCategory::Asset, 'type' => AccountType::NonCurrentAsset, 'subtype' => 'Property, Plant & Equipment'],
            ['code' => '1510', 'name' => 'Furniture & Fixtures', 'category' => AccountCategory::Asset, 'type' => AccountType::NonCurrentAsset, 'subtype' => 'Property, Plant & Equipment'],
            ['code' => '1600', 'name' => 'Accumulated Depreciation', 'category' => AccountCategory::Asset, 'type' => AccountType::ContraAsset, 'subtype' => 'Accumulated Depreciation'],

            // ── Liabilities ─────────────────────────────────────────
            ['code' => '2000', 'name' => 'Accounts Payable', 'category' => AccountCategory::Liability, 'type' => AccountType::CurrentLiability, 'subtype' => 'Accounts Payable', 'default' => true],
            ['code' => '2100', 'name' => 'Accrued Expenses', 'category' => AccountCategory::Liability, 'type' => AccountType::CurrentLiability, 'subtype' => 'Accrued Liabilities'],
            ['code' => '2200', 'name' => 'Sales Tax Payable', 'category' => AccountCategory::Liability, 'type' => AccountType::CurrentLiability, 'subtype' => 'Taxes Payable'],
            ['code' => '2300', 'name' => 'Unearned Revenue', 'category' => AccountCategory::Liability, 'type' => AccountType::CurrentLiability, 'subtype' => 'Unearned Revenue'],
            ['code' => '2500', 'name' => 'Long-Term Loan', 'category' => AccountCategory::Liability, 'type' => AccountType::NonCurrentLiability, 'subtype' => 'Long-Term Debt'],

            // ── Equity ──────────────────────────────────────────────
            ['code' => '3000', 'name' => 'Owner\'s Capital', 'category' => AccountCategory::Equity, 'type' => AccountType::Equity, 'subtype' => 'Owner\'s Capital'],
            ['code' => '3100', 'name' => 'Retained Earnings', 'category' => AccountCategory::Equity, 'type' => AccountType::Equity, 'subtype' => 'Retained Earnings', 'default' => true],
            ['code' => '3200', 'name' => 'Owner\'s Drawings', 'category' => AccountCategory::Equity, 'type' => AccountType::ContraEquity, 'subtype' => 'Owner\'s Drawings'],

            // ── Revenue ─────────────────────────────────────────────
            ['code' => '4000', 'name' => 'Sales Revenue', 'category' => AccountCategory::Revenue, 'type' => AccountType::OperatingRevenue, 'subtype' => 'Sales Revenue', 'default' => true],
            ['code' => '4100', 'name' => 'Service Revenue', 'category' => AccountCategory::Revenue, 'type' => AccountType::OperatingRevenue, 'subtype' => 'Service Revenue'],
            ['code' => '4200', 'name' => 'Interest Income', 'category' => AccountCategory::Revenue, 'type' => AccountType::NonOperatingRevenue, 'subtype' => 'Interest Income'],
            ['code' => '4300', 'name' => 'Other Income', 'category' => AccountCategory::Revenue, 'type' => AccountType::NonOperatingRevenue, 'subtype' => 'Other Income'],
            ['code' => '4400', 'name' => 'Sales Returns & Allowances', 'category' => AccountCategory::Revenue, 'type' => AccountType::ContraRevenue, 'subtype' => 'Sales Returns & Allowances'],

            // ── Expenses ────────────────────────────────────────────
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'subtype' => 'Cost of Goods Sold', 'default' => true],
            ['code' => '5100', 'name' => 'Salaries & Wages', 'category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'subtype' => 'Payroll & Wages'],
            ['code' => '5200', 'name' => 'Rent Expense', 'category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'subtype' => 'Rent & Lease'],
            ['code' => '5300', 'name' => 'Utilities Expense', 'category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'subtype' => 'Utilities'],
            ['code' => '5400', 'name' => 'Office Supplies Expense', 'category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'subtype' => 'Office Supplies'],
            ['code' => '5500', 'name' => 'Insurance Expense', 'category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'subtype' => 'Insurance'],
            ['code' => '5600', 'name' => 'Depreciation Expense', 'category' => AccountCategory::Expense, 'type' => AccountType::OperatingExpense, 'subtype' => 'Depreciation'],
            ['code' => '5700', 'name' => 'Interest Expense', 'category' => AccountCategory::Expense, 'type' => AccountType::NonOperatingExpense, 'subtype' => 'Interest Expense'],
            ['code' => '5800', 'name' => 'Bank Service Charges', 'category' => AccountCategory::Expense, 'type' => AccountType::NonOperatingExpense, 'subtype' => 'Bank Fees'],
        ];

        foreach ($accounts as $accountData) {
            $subtypeName = $accountData['subtype'] ?? null;
            unset($accountData['subtype']);

            $subtypeId = null;
            if ($subtypeName) {
                $subtype = AccountSubtype::where('company_id', $companyId)
                    ->where('name', $subtypeName)
                    ->first();
                $subtypeId = $subtype?->id;
            }

            Account::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'code' => $accountData['code'],
                ],
                array_merge($accountData, [
                    'company_id' => $companyId,
                    'subtype_id' => $subtypeId,
                    'currency_code' => $currencyCode,
                    'default' => $accountData['default'] ?? false,
                ])
            );
        }
    }
}
