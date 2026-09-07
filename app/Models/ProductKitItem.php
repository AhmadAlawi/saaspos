<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One component line under a `type = 'kit'` parent product.
 *
 * A kit is a bundle SKU — a single ringable item that represents N
 * underlying products (and/or variants) shipped together. Selling the
 * parent draws down each component's stock by `quantity` at sale time.
 *
 * `component_variant_id` is optional: when set, the line locks to a
 * specific variant of the component product (e.g. "include the BBQ
 * flavor"); when null, any active variant / the bare simple SKU fills
 * the slot.
 */
class ProductKitItem extends Model
{
    protected $fillable = [
        'parent_product_id',
        'component_product_id',
        'component_variant_id',
        'quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'component_variant_id');
    }
}
