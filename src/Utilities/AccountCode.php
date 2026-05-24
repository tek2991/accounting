<?php

namespace Tek2991\Accounting\Utilities;

use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\AccountSubtype;
use Tek2991\Accounting\Enums\AccountCategory;

class AccountCode
{
    /**
     * Generate the next available account code for the given subtype.
     *
     * Finds the highest existing code in the category's range and increments by 10,
     * or returns the category start if no accounts exist yet.
     */
    public static function generate(AccountSubtype $subtype, ?int $companyId = null): string
    {
        $category = $subtype->category;
        $start = $category->getCodeRangeStart();
        $end   = $category->getCodeRangeEnd();

        $query = Account::query()
            ->where('category', $category)
            ->whereBetween('code', [(string) $start, (string) $end]);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $maxCode = $query
            ->orderByRaw('CAST(code AS UNSIGNED) DESC')
            ->value('code');

        if ($maxCode === null) {
            return (string) $start;
        }

        $next = ((int) $maxCode) + 10;

        // If we've exhausted the range, try incrementing by 1
        if ($next > $end) {
            $next = ((int) $maxCode) + 1;
        }

        // If still out of range, just return max + 1 and let validation catch it
        return (string) min($next, $end);
    }

    /**
     * Suggest a code within the category range, for use in the AccountChart form.
     * Accepts a category directly (when subtype is not yet chosen).
     */
    public static function generateForCategory(AccountCategory $category, ?int $companyId = null): string
    {
        $start = $category->getCodeRangeStart();
        $end   = $category->getCodeRangeEnd();

        $query = Account::query()
            ->where('category', $category)
            ->whereBetween('code', [(string) $start, (string) $end]);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $maxCode = $query
            ->orderByRaw('CAST(code AS UNSIGNED) DESC')
            ->value('code');

        if ($maxCode === null) {
            return (string) $start;
        }

        $next = ((int) $maxCode) + 10;

        return (string) min($next, $end);
    }
}
