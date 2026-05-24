<?php

namespace Tek2991\Accounting\Services;

use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Exceptions\FiscalPeriodLockedException;
use App\Models\User;

class FiscalPeriodService
{
    /**
     * Check if a date falls within a locked period.
     * Throws FiscalPeriodLockedException if locked.
     */
    public function assertNotLocked(int $companyId, string $date): void
    {
        $lockedPeriod = FiscalPeriod::query()
            ->where('company_id', $companyId)
            ->whereNotNull('locked_at')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if ($lockedPeriod) {
            throw new FiscalPeriodLockedException("The posting date falls within a locked fiscal period: {$lockedPeriod->name}.");
        }
    }

    /**
     * Lock a period.
     */
    public function lock(FiscalPeriod $period, User $lockedBy): void
    {
        $period->update([
            'locked_at' => now(),
            'locked_by' => $lockedBy->id,
        ]);
        
        // Log the lock action for audit trail
        activity('accounting')
            ->performedOn($period)
            ->causedBy($lockedBy)
            ->log("Fiscal period locked");
    }

    /**
     * Unlock a period.
     */
    public function unlock(FiscalPeriod $period, User $unlockedBy): void
    {
        $period->update([
            'locked_at' => null,
            'locked_by' => null,
        ]);
        
        // Log the unlock action prominently
        activity('accounting')
            ->performedOn($period)
            ->causedBy($unlockedBy)
            ->log("Fiscal period UNLOCKED (Emergency Action)");
    }
}
