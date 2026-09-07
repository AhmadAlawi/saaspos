<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ledger account (e.g. "1010 Cash on Hand"). Every journal line debits or
 * credits exactly one of these. `code` is immutable once referenced by a
 * posted entry; `is_system` accounts back the built-in business-event
 * mappings and can't be deleted while referenced.
 */
class Account extends Model
{
    protected $fillable = [
        'account_group_id', 'code', 'name', 'type', 'currency_code', 'is_system', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
