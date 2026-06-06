<?php

namespace Tek2991\Accounting\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\BankAccountType;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Enums\TaxType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\BankAccount;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Models\Tax;

class DemoDataSeeder extends Seeder
{
    public function run(int $companyId): void
    {
        DB::transaction(function () use ($companyId) {
            
            // 1. Fiscal Year (April 1 to March 31)
            $currentYear = now()->year;
            $startYear = now()->month >= 4 ? $currentYear : $currentYear - 1;
            
            FiscalPeriod::firstOrCreate([
                'company_id' => $companyId,
                'name' => "FY {$startYear}-" . substr($startYear + 1, 2),
            ], [
                'start_date' => Carbon::create($startYear, 4, 1)->startOfDay(),
                'end_date' => Carbon::create($startYear + 1, 3, 31)->endOfDay(),
            ]);

            // 2. Chart of Accounts for Bank Accounts
            $hdfcCurrentAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'HDFC Current Account',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $sbiCurrentAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'SBI Current Account',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $hdfcSavingsAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'HDFC Savings Account',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $sbiSavingsAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'SBI Savings Account',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            // 3. Respective Bank Accounts
            BankAccount::firstOrCreate([
                'company_id' => $companyId,
                'account_id' => $hdfcCurrentAccount->id,
            ], [
                'type' => BankAccountType::Depository,
                'nickname' => 'HDFC Current',
                'number' => '1234567890',
                'enabled' => true,
            ]);

            BankAccount::firstOrCreate([
                'company_id' => $companyId,
                'account_id' => $sbiCurrentAccount->id,
            ], [
                'type' => BankAccountType::Depository,
                'nickname' => 'SBI Current',
                'number' => '0987654321',
                'enabled' => false,
            ]);

            BankAccount::firstOrCreate([
                'company_id' => $companyId,
                'account_id' => $hdfcSavingsAccount->id,
            ], [
                'type' => BankAccountType::Depository, // Savings is technically depository
                'nickname' => 'HDFC Savings',
                'number' => '1122334455',
                'enabled' => false,
            ]);

            BankAccount::firstOrCreate([
                'company_id' => $companyId,
                'account_id' => $sbiSavingsAccount->id,
            ], [
                'type' => BankAccountType::Depository,
                'nickname' => 'SBI Savings',
                'number' => '5566778899',
                'enabled' => false,
            ]);

            // 4. Contacts
            Contact::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Reliance Retail (Vendor)',
            ], [
                'type' => ContactType::Vendor,
                'email' => 'vendor@reliance.com',
                'tax_id' => '27AADCR1234A1Z5', // Dummy GSTIN
            ]);

            Contact::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Tata Consultancy Services (Customer)',
            ], [
                'type' => ContactType::Customer,
                'email' => 'accounts@tcs.com',
                'tax_id' => '27AAACT1234A1Z5',
            ]);
            
            Contact::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Ramesh Kumar (Employee)',
            ], [
                'type' => ContactType::Both,
                'email' => 'ramesh@demo.com',
            ]);

            // 5. Taxes (GST 18%)
            // Create Ledger Accounts for CGST and SGST
            $outputCgstAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Output CGST @ 9%',
            ], [
                'type' => AccountType::Liability,
                'reporting_class' => ReportingClass::CurrentLiability,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $outputSgstAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Output SGST @ 9%',
            ], [
                'type' => AccountType::Liability,
                'reporting_class' => ReportingClass::CurrentLiability,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $inputCgstAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Input CGST @ 9%',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $inputSgstAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Input SGST @ 9%',
            ], [
                'type' => AccountType::Asset,
                'reporting_class' => ReportingClass::CurrentAsset,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            // Exclusive Tax
            $gst18Exclusive = Tax::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'GST @ 18% (Exclusive)',
            ], [
                'type' => TaxType::Exclusive,
                'description' => '9% CGST + 9% SGST',
                'is_active' => true,
            ]);

            if ($gst18Exclusive->wasRecentlyCreated) {
                $gst18Exclusive->components()->create([
                    'name' => 'CGST',
                    'rate' => 9.00,
                    'sales_account_id' => $outputCgstAccount->id,
                    'purchase_account_id' => $inputCgstAccount->id,
                ]);
                $gst18Exclusive->components()->create([
                    'name' => 'SGST',
                    'rate' => 9.00,
                    'sales_account_id' => $outputSgstAccount->id,
                    'purchase_account_id' => $inputSgstAccount->id,
                ]);
            }

            // Inclusive Tax
            $gst18Inclusive = Tax::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'GST @ 18% (Inclusive)',
            ], [
                'type' => TaxType::Inclusive,
                'description' => '9% CGST + 9% SGST',
                'is_active' => true,
            ]);

            if ($gst18Inclusive->wasRecentlyCreated) {
                $gst18Inclusive->components()->create([
                    'name' => 'CGST',
                    'rate' => 9.00,
                    'sales_account_id' => $outputCgstAccount->id,
                    'purchase_account_id' => $inputCgstAccount->id,
                ]);
                $gst18Inclusive->components()->create([
                    'name' => 'SGST',
                    'rate' => 9.00,
                    'sales_account_id' => $outputSgstAccount->id,
                    'purchase_account_id' => $inputSgstAccount->id,
                ]);
            }

            // 6. Items
            // Ensure Income and Expense accounts exist for mapping
            $salesAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Sales Revenue',
            ], [
                'type' => AccountType::Revenue,
                'reporting_class' => ReportingClass::Revenue,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            $cogsAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Cost of Goods Sold',
            ], [
                'type' => AccountType::Expense,
                'reporting_class' => ReportingClass::COGS,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);
            
            $servicesExpenseAccount = Account::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Subcontractor Services',
            ], [
                'type' => AccountType::Expense,
                'reporting_class' => ReportingClass::OperatingExpense,
                'currency_code' => \Tek2991\Accounting\Facades\Accounting::getCurrency(),
            ]);

            \Tek2991\Accounting\Models\Item::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'Web Development Service',
            ], [
                'type' => \Tek2991\Accounting\Enums\ItemType::Services,
                'sku' => 'SRV-WEB-001',
                'description' => 'Custom Web Application Development',
                'hsn_sac' => '998314', // SAC code for IT services
                'income_account_id' => $salesAccount->id,
                'expense_account_id' => $servicesExpenseAccount->id,
                'sale_price' => 50000,
                'purchase_price' => 0,
                'sellable' => true,
                'purchasable' => false,
            ]);

            \Tek2991\Accounting\Models\Item::firstOrCreate([
                'company_id' => $companyId,
                'name' => 'MacBook Pro 16" (M3 Max)',
            ], [
                'type' => \Tek2991\Accounting\Enums\ItemType::Goods,
                'sku' => 'HW-MBP-16-M3M',
                'description' => 'Apple MacBook Pro 16-inch with M3 Max chip',
                'hsn_sac' => '84713010', // HSN code for Laptops
                'income_account_id' => $salesAccount->id,
                'expense_account_id' => $cogsAccount->id,
                'sale_price' => 350000,
                'purchase_price' => 310000,
                'sellable' => true,
                'purchasable' => true,
            ]);
        });
    }
}
