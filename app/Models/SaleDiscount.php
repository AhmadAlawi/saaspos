<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The order-level discount applied to a sale, with its audit trail —
 * type/value the cashier entered, the resolved amount, and the optional
 * reason + category. One row per sale when an order-level discount is
 * applied (Checkout discounts, Slice 3).
 */
class SaleDiscount extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'type',            // 'pct' | 'amt'
        'value',           // percent (0–100) or currency amount the cashier entered
        'amount',          // resolved discount amount actually applied
        'reason',
        'reason_category',
        'applied_by',
    ];

    protected function casts(): array
    {
        return [
            'value'  => 'decimal:4',
            'amount' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<User, $this> */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
