<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pre-aggregated day of trading for one store — optionally scoped to a
 * single cashier (a per-cashier row) or the whole store (the aggregate row,
 * `cashier_id` = null). Recomputed by {@see \App\Actions\Reports\RefreshDailyMetrics}
 * and read back — with a live fallback for un-settled days — by
 * {@see \App\Services\Reports\DailyMetricsReader}.
 *
 * `refresh_status`:
 *   - `current` — the row reflects the underlying sales; safe to read.
 *   - `stale`   — a later void/return touched this day; read live until the
 *                  nightly refresh recomputes it.
 */
class DailyMetric extends Model
{
    public const STATUS_CURRENT = 'current';
    public const STATUS_STALE   = 'stale';

    protected $fillable = [
        'store_id', 'date', 'cashier_id',
        'sales_count', 'sales_gross', 'sales_returns', 'sales_net',
        'tax_total', 'cogs', 'gross_profit',
        'cash_total', 'card_total', 'upi_total', 'gateway_total',
        'store_credit_total', 'customer_credit_total',
        'discounts_total', 'transactions_voided',
        'avg_basket_size', 'items_sold', 'new_customers_acquired',
        'refresh_status',
    ];

    protected $casts = [
        'date'                  => 'date',
        'sales_count'           => 'integer',
        'transactions_voided'   => 'integer',
        'new_customers_acquired' => 'integer',
        'sales_gross'           => 'decimal:4',
        'sales_returns'         => 'decimal:4',
        'sales_net'             => 'decimal:4',
        'tax_total'             => 'decimal:4',
        'cogs'                  => 'decimal:4',
        'gross_profit'          => 'decimal:4',
        'cash_total'            => 'decimal:4',
        'card_total'            => 'decimal:4',
        'upi_total'             => 'decimal:4',
        'gateway_total'         => 'decimal:4',
        'store_credit_total'    => 'decimal:4',
        'customer_credit_total' => 'decimal:4',
        'discounts_total'       => 'decimal:4',
        'avg_basket_size'       => 'decimal:4',
        'items_sold'            => 'decimal:4',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
