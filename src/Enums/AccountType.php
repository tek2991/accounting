<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccountType: string implements HasLabel
{
    // Asset types
    case CurrentAsset = 'current_asset';
    case NonCurrentAsset = 'non_current_asset';
    case ContraAsset = 'contra_asset';

    // Liability types
    case CurrentLiability = 'current_liability';
    case NonCurrentLiability = 'non_current_liability';
    case ContraLiability = 'contra_liability';

    // Equity types
    case Equity = 'equity';
    case ContraEquity = 'contra_equity';

    // Revenue types
    case OperatingRevenue = 'operating_revenue';
    case NonOperatingRevenue = 'non_operating_revenue';
    case ContraRevenue = 'contra_revenue';

    // Expense types
    case OperatingExpense = 'operating_expense';
    case NonOperatingExpense = 'non_operating_expense';
    case ContraExpense = 'contra_expense';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::CurrentAsset => 'Current Asset',
            self::NonCurrentAsset => 'Non-Current Asset',
            self::ContraAsset => 'Contra Asset',
            self::CurrentLiability => 'Current Liability',
            self::NonCurrentLiability => 'Non-Current Liability',
            self::ContraLiability => 'Contra Liability',
            self::Equity => 'Equity',
            self::ContraEquity => 'Contra Equity',
            self::OperatingRevenue => 'Operating Revenue',
            self::NonOperatingRevenue => 'Non-Operating Revenue',
            self::ContraRevenue => 'Contra Revenue',
            self::OperatingExpense => 'Operating Expense',
            self::NonOperatingExpense => 'Non-Operating Expense',
            self::ContraExpense => 'Contra Expense',
        };
    }

    /**
     * Get the parent category for this account type.
     */
    public function getCategory(): AccountCategory
    {
        return match ($this) {
            self::CurrentAsset, self::NonCurrentAsset, self::ContraAsset => AccountCategory::Asset,
            self::CurrentLiability, self::NonCurrentLiability, self::ContraLiability => AccountCategory::Liability,
            self::Equity, self::ContraEquity => AccountCategory::Equity,
            self::OperatingRevenue, self::NonOperatingRevenue, self::ContraRevenue => AccountCategory::Revenue,
            self::OperatingExpense, self::NonOperatingExpense, self::ContraExpense => AccountCategory::Expense,
        };
    }

    /**
     * Whether this is a contra account type.
     * Contra accounts have the opposite natural balance of their category.
     */
    public function isContra(): bool
    {
        return in_array($this, [
            self::ContraAsset,
            self::ContraLiability,
            self::ContraEquity,
            self::ContraRevenue,
            self::ContraExpense,
        ]);
    }

    /**
     * Get the natural balance type, accounting for contra accounts.
     */
    public function getBalanceType(): JournalEntryType
    {
        $naturalBalance = $this->getCategory()->getDefaultBalanceType();

        if ($this->isContra()) {
            return $naturalBalance === JournalEntryType::Debit
                ? JournalEntryType::Credit
                : JournalEntryType::Debit;
        }

        return $naturalBalance;
    }

    /**
     * Get all types for a given category.
     *
     * @return array<self>
     */
    public static function forCategory(AccountCategory $category): array
    {
        return array_filter(
            self::cases(),
            fn (self $type) => $type->getCategory() === $category
        );
    }

    /**
     * Whether this type appears on the Balance Sheet.
     */
    public function isBalanceSheet(): bool
    {
        return $this->getCategory()->isReal();
    }

    /**
     * Whether this type appears on the Income Statement.
     */
    public function isIncomeStatement(): bool
    {
        return $this->getCategory()->isNominal();
    }
}
