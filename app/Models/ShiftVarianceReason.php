<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Picklist of "why is there cash variance" reasons the cashier picks
 * from when closing a shift outside the configured tolerance. Mirrors
 * StockAdjustmentReason / ReturnReason shape (code + name + sort_order
 * + is_active). Seeded with the common ones (miscount, theft_suspected,
 * change_dispute, unaccounted_pay_out, other) — operators can extend
 * the list to match their local taxonomy.
 */
class ShiftVarianceReason extends Model
{
    protected $fillable = ['code', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @param Builder<ShiftVarianceReason> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<ShiftVarianceReason> $q */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderBy('sort_order')->orderBy('code');
    }
}
