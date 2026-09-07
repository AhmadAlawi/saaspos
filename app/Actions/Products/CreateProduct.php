<?php

namespace App\Actions\Products;

use App\Actions\Products\Concerns\ReclaimsSoftDeletedIdentifiers;
use App\Events\ProductCreated;
use App\Models\Product;

/**
 * Persist a new product.
 *
 * Extension points:
 *   - filter `product.attributes`     → modify the attribute array
 *   - action `product.before_create`  → side-effects before insert
 *   - action `product.after_create`   → side-effects after insert
 *   - event  ProductCreated           → decoupled listeners
 */
class CreateProduct
{
    use ReclaimsSoftDeletedIdentifiers;

    /** @param  array<string, mixed>  $data  Already-validated payload from ProductRequest. */
    public function __invoke(array $data): Product
    {
        $data = apply_filters('product.attributes', $data);
        do_action('product.before_create', $data);

        // A deleted product's SKU is meant to be reusable (ProductRequest only
        // checks non-trashed rows), but the DB unique index still holds it —
        // reclaim it from any trashed row before inserting, or the insert 1062s.
        $this->reclaimSoftDeletedIdentifiers($data['sku'] ?? null, $data['barcode'] ?? null);

        $product = Product::create($data);

        do_action('product.after_create', $product);
        event(new ProductCreated($product));

        return $product;
    }
}
