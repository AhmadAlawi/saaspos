<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-store (and per-variant) stock projection.
 *
 * One row per (store, product, variant) — `variant_id` null for simple
 * products. Mutated exclusively through
 * {@see \App\Actions\Inventory\RecordStockMovement} so the level and the
 * `stock_movements` ledger never diverge.
 *
 * `reorder_level_override` shadows the product-level default; null = use
 * the product's value. `weighted_average_cost` updates on positive,
 * costed movements.
 */
class StockLevel extends Model
{
    protected $table = 'product_stock_levels';

    protected $fillable = [
        'store_id', 'product_id', 'variant_id',
        'quantity', 'reserved_quantity', 'weighted_average_cost',
        'reorder_level_override', 'last_movement_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity'               => 'decimal:4',
            'reserved_quantity'      => 'decimal:4',
            'weighted_average_cost'  => 'decimal:4',
            'reorder_level_override' => 'decimal:4',
            'last_movement_at'       => 'datetime',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
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
}
