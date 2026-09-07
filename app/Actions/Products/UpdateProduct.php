<?php

namespace App\Actions\Products;

use App\Actions\Products\Concerns\ReclaimsSoftDeletedIdentifiers;
use App\Events\ProductUpdated;
use App\Models\Product;

/**
 * Update an existing product.
 *
 * Extension points:
 *   - filter `product.attributes`     → modify the attribute array (shared with create)
 *   - action `product.before_update`  → side-effects before save; receives ($product, $data)
 *   - action `product.after_update`   → side-effects after save;  receives ($product, $original)
 *   - event  ProductUpdated           → decoupled listeners
 */
class UpdateProduct
{
    use ReclaimsSoftDeletedIdentifiers;

    /** @param  array<string, mixed>  $data  Already-validated payload from ProductRequest. */
    public function __invoke(Product $product, array $data): Product
    {
        $original = $product->getOriginal();

        $data = apply_filters('product.attributes', $data, $product);
        do_action('product.before_update', $product, $data);

        // Reclaim a SKU/barcode held by a trashed product if this rename would
        // otherwise collide with the all-rows unique index (ignore self).
        $this->reclaimSoftDeletedIdentifiers($data['sku'] ?? null, $data['barcode'] ?? null, $product->id);

        $product->update($data);

        do_action('product.after_update', $product, $original);
        event(new ProductUpdated($product, $original));

        return $product;
    }
}
