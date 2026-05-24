<?php

namespace Tek2991\Accounting\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BankAccountType: string implements HasLabel, HasColor, HasIcon
{
    case Depository = 'depository';
    case Credit = 'credit';
    case Investment = 'investment';
    case Loan = 'loan';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Depository => 'Depository',
            self::Credit     => 'Credit Card',
            self::Investment => 'Investment',
            self::Loan       => 'Loan',
            self::Other      => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Depository => 'success',
            self::Credit     => 'danger',
            self::Investment => 'info',
            self::Loan       => 'warning',
            self::Other      => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Depository => 'heroicon-o-building-library',
            self::Credit     => 'heroicon-o-credit-card',
            self::Investment => 'heroicon-o-chart-bar-square',
            self::Loan       => 'heroicon-o-banknotes',
            self::Other      => 'heroicon-o-ellipsis-horizontal-circle',
        };
    }

    /**
     * The AccountCategory this bank account type belongs to.
     * Depository and Investment are Asset accounts.
     * Credit and Loan are Liability accounts.
     */
    public function getAccountCategory(): AccountCategory
    {
        return match ($this) {
            self::Depository, self::Investment => AccountCategory::Asset,
            self::Credit, self::Loan           => AccountCategory::Liability,
            self::Other                        => AccountCategory::Asset,
        };
    }

    /**
     * The default AccountSubtype name for this bank account type.
     * Used when seeding or auto-selecting a subtype.
     */
    public function getDefaultSubtype(): string
    {
        return match ($this) {
            self::Depository => 'Cash and Cash Equivalents',
            self::Credit     => 'Accounts Payable',
            self::Investment => 'Intangible Assets',
            self::Loan       => 'Long-Term Debt',
            self::Other      => 'Cash and Cash Equivalents',
        };
    }

    /**
     * Get the AccountType values valid for this BankAccountType.
     *
     * @return AccountType[]
     */
    public function getValidAccountTypes(): array
    {
        return match ($this) {
            self::Depository => [AccountType::CurrentAsset],
            self::Investment => [AccountType::NonCurrentAsset],
            self::Credit     => [AccountType::CurrentLiability],
            self::Loan       => [AccountType::NonCurrentLiability],
            self::Other      => [AccountType::CurrentAsset, AccountType::NonCurrentAsset],
        };
    }
}
