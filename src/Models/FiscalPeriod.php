<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tek2991\Accounting\Concerns\CompanyOwned;
use App\Models\User;

class FiscalPeriod extends Model
{
    use CompanyOwned;

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'locked_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'fiscal_periods';
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->locked_at !== null;
    }
}
