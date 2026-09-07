<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-store (and optionally per-variant) price override.
 *
 * Maps to `product_store_prices` (created in the inventory migration).
 * Any of `cost_price` / `selling_price` / `mrp` may be null — nulls
 * fall through to the variant's, then the product's, defaults at
 * resolve time. See {@see App\Actions\Products\ResolveProductPrice}.
 *
 * `variant_id` null = product-level override (simple / kit products);
 * set = variant-level override (variant products price per child).
 */
class ProductStorePrice extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'variant_id',
        'cost_price',
        'selling_price',
        'mrp',
    ];

    protected function casts(): array
    {
        return [
            'cost_price'    => 'decimal:4',
            'selling_price' => 'decimal:4',
            'mrp'           => 'decimal:4',
        ];
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
