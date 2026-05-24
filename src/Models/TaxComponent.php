<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class TaxComponent extends Model
{
    use SoftDeletes;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('catalog')
            ->setDescriptionForEvent(fn(string $eventName) => "This tax component has been {$eventName}");
    }

    protected $fillable = [
        'tax_id',
        'name',
        'rate',
        'account_id',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
    ];

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'tax_components';
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
