<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row on a Stock Take. `expected_quantity` is the snapshot of
 * `product_stock_levels.quantity` at the moment the take was created;
 * `counted_quantity` is what the operator entered after walking the
 * shelves. `null` counted_quantity means "skipped / not yet counted"
 * — it's NOT zero, and a skipped line never writes a movement.
 *
 * Variance = counted − expected; only non-null + non-zero variance
 * lines flow to the ledger on post.
 */
class StockTakeItem extends Model
{
    protected $fillable = [
        'stock_take_id', 'product_id', 'variant_id',
        'expected_quantity', 'counted_quantity', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:4',
            'counted_quantity'  => 'decimal:4',
        ];
    }

    /**
     * Signed variance — counted minus expected. Null when the line
     * hasn't been counted yet.
     */
    public function variance(): ?string
    {
        if ($this->counted_quantity === null) return null;
        return bcsub((string) $this->counted_quantity, (string) $this->expected_quantity, 4);
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    public function hasVariance(): bool
    {
        $v = $this->variance();
        return $v !== null && bccomp($v, '0', 4) !== 0;
    }

    /** @return BelongsTo<StockTake, $this> */
    public function take(): BelongsTo
    {
        return $this->belongsTo(StockTake::class, 'stock_take_id');
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
