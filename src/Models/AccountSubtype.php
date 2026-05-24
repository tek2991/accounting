<?php

namespace Tek2991\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tek2991\Accounting\Concerns\CompanyOwned;
use Tek2991\Accounting\Enums\AccountCategory;
use Tek2991\Accounting\Enums\AccountType;

class AccountSubtype extends Model
{
    use CompanyOwned;

    protected $fillable = [
        'company_id',
        'category',
        'type',
        'name',
        'description',
    ];

    protected $casts = [
        'category' => AccountCategory::class,
        'type' => AccountType::class,
    ];

    public $timestamps = false;

    public function getTable(): string
    {
        return config('accounting.table_prefix', 'acc_') . 'account_subtypes';
    }

    /**
     * Accounts belonging to this subtype.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'subtype_id');
    }
}
