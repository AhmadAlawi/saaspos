<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bucket expenses are filed under (Rent, Utilities, Salaries, …). The
 * optional `account_id` links to the chart of accounts for the deferred
 * Accounting slice; expenses work fine without it.
 */
class ExpenseCategory extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'account_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    /** @param Builder<ExpenseCategory> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<ExpenseCategory> $q */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderBy('name');
    }
}
