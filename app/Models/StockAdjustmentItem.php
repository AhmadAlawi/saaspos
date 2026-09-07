<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single line on a stock adjustment. `quantity_delta` is signed —
 * positive for inflow (recount up, opening stock, returned from internal
 * use), negative for outflow (damaged, lost, expired, internal use,
 * recount down). `unit_cost` is optional but required for positive
 * deltas you want to roll into the weighted-average cost.
 */
class StockAdjustmentItem extends Model
{
    public $timestamps = false; // no created_at / updated_at on this table

    protected $fillable = [
        'stock_adjustment_id', 'product_id', 'variant_id', 'batch_id',
        'batch_number', 'manufacture_date', 'expiry_date',
        'quantity_delta', 'unit_cost', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_date' => 'date',
            'expiry_date'      => 'date',
            'quantity_delta'   => 'decimal:4',
            'unit_cost'        => 'decimal:4',
        ];
    }

    /** @return BelongsTo<StockAdjustment, $this> */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<\App\Models\ProductBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProductBatch::class);
    }
}
