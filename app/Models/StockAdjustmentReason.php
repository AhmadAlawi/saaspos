<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Picklist of "why was stock changed" reasons offered on the stock-
 * adjustment editor. Seeded with the common ones (OPENING, DAMAGED,
 * EXPIRED, LOST, RECOUNT, RETURN_TO_VENDOR, INTERNAL_USE, OTHER) — the
 * installer can add their own from this module.
 */
class StockAdjustmentReason extends Model
{
    protected $fillable = ['code', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<StockAdjustmentReason> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<StockAdjustmentReason> $q */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderBy('sort_order')->orderBy('code');
    }
}
