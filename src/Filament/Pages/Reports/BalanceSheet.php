<?php

namespace Tek2991\Accounting\Filament\Pages\Reports;

use Tek2991\Accounting\Filament\Pages\Reports\Concerns\HasReportExports;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Services\AccountService;
use Carbon\Carbon;

class BalanceSheet extends Page implements HasForms
{
    use InteractsWithForms;
    use HasReportExports;

    protected static \UnitEnum|string|null $navigationGroup = 'Reports';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'accounting::filament.pages.reports.balance-sheet';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'end_date' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('end_date')
                    ->label('As of Date')
                    ->required()
                    ->default(now())
                    ->live()
            ])
            ->statePath('data');
    }

    public function getReportDataProperty(): array
    {
        $endDate = $this->data['end_date'] ?? Carbon::now()->format('Y-m-d');
        // Balance sheet considers all history up to end_date
        $startDate = '1970-01-01'; 

        $service = app(AccountService::class);

        $assets = collect($service->getTypeBalances(AccountType::Asset, $startDate, $endDate));
        $liabilities = collect($service->getTypeBalances(AccountType::Liability, $startDate, $endDate));
        $equity = collect($service->getTypeBalances(AccountType::Equity, $startDate, $endDate));

        $totalAssets = $service->getTypeTotal(AccountType::Asset, $startDate, $endDate);
        $totalLiabilities = $service->getTypeTotal(AccountType::Liability, $startDate, $endDate);
        $totalEquity = $service->getTypeTotal(AccountType::Equity, $startDate, $endDate);

        // Calculate Retained Earnings if P&L is closed to equity?
        // Actually, current period net income is part of Equity.
        // We need to add Net Income for the period (all time to end date) to Equity!
        $revenueTotal = $service->getTypeTotal(AccountType::Revenue, $startDate, $endDate);
        $expenseTotal = $service->getTypeTotal(AccountType::Expense, $startDate, $endDate);
        $netIncomeAmount = $revenueTotal->getAmount() - $expenseTotal->getAmount();
        $netIncome = new \Tek2991\Accounting\ValueObjects\Money($netIncomeAmount, \Tek2991\Accounting\Facades\Accounting::getCurrency());
        
        $totalLiabilitiesAndEquityAmount = $totalLiabilities->getAmount() + $totalEquity->getAmount() + $netIncomeAmount;
        $totalLiabilitiesAndEquity = new \Tek2991\Accounting\ValueObjects\Money($totalLiabilitiesAndEquityAmount, \Tek2991\Accounting\Facades\Accounting::getCurrency());

        return [
            'title' => 'Balance Sheet',
            'assets' => $this->groupBalancesByClass($assets),
            'liabilities' => $this->groupBalancesByClass($liabilities),
            'equity' => $this->groupBalancesByClass($equity),
            'netIncome' => $netIncome,
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity' => $totalEquity,
            'totalLiabilitiesAndEquity' => $totalLiabilitiesAndEquity,
            'endDate' => Carbon::parse($endDate)->format('F j, Y'),
        ];
    }

    public function generateCsvRows($handle, $data): void
    {
        fputcsv($handle, ['Category', 'Account Code', 'Account Name', 'Balance']);
        
        fputcsv($handle, ['ASSETS', '', '', '']);
        foreach ($data['assets'] as $class => $items) {
            fputcsv($handle, [$class, '', '', '']);
            foreach ($items as $row) {
                fputcsv($handle, ['', $row['account']->code ?? '', $row['account']->name, $row['balance']->getDecimal()]);
            }
        }
        fputcsv($handle, ['Total Assets', '', '', $data['totalAssets']->getDecimal()]);
        fputcsv($handle, []);

        fputcsv($handle, ['LIABILITIES', '', '', '']);
        foreach ($data['liabilities'] as $class => $items) {
            fputcsv($handle, [$class, '', '', '']);
            foreach ($items as $row) {
                fputcsv($handle, ['', $row['account']->code ?? '', $row['account']->name, $row['balance']->getDecimal()]);
            }
        }
        fputcsv($handle, ['Total Liabilities', '', '', $data['totalLiabilities']->getDecimal()]);
        fputcsv($handle, []);

        fputcsv($handle, ['EQUITY', '', '', '']);
        foreach ($data['equity'] as $class => $items) {
            fputcsv($handle, [$class, '', '', '']);
            foreach ($items as $row) {
                fputcsv($handle, ['', $row['account']->code ?? '', $row['account']->name, $row['balance']->getDecimal()]);
            }
        }
        fputcsv($handle, ['Retained Earnings / Net Income', '', '', $data['netIncome']->getDecimal()]);
        fputcsv($handle, ['Total Equity', '', '', ($data['totalEquity']->getAmount() + $data['netIncome']->getAmount()) / 100]);
        fputcsv($handle, []);
        
        fputcsv($handle, ['Total Liabilities & Equity', '', '', $data['totalLiabilitiesAndEquity']->getDecimal()]);
    }

    protected function groupBalancesByClass(\Illuminate\Support\Collection $balances): \Illuminate\Support\Collection
    {
        // Filter out zero balances for cleaner reports
        return $balances->filter(function ($item) {
            return $item['balance']->getAmount() !== 0;
        })->groupBy(function ($item) {
            return $item['account']->reporting_class->getLabel();
        });
    }
}
