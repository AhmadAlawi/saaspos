<?php

namespace App\Actions\Products;

use App\Events\ProductUpdated;
use App\Models\Product;
use InvalidArgumentException;

/**
 * Flip a boolean flag on a product without going through the full
 * validation pipeline. Used by the row-level quick-action buttons
 * (status toggle, featured star) on the products list page.
 *
 * Only `is_active` and `is_featured` are flippable — extending the
 * whitelist requires a code change to keep the surface tight.
 *
 * Fires the same `product.before_update` / `product.after_update`
 * hooks and `ProductUpdated` event as a normal save, so listeners
 * (audit log, cache invalidation) see the change.
 */
class ToggleProductFlag
{
    private const ALLOWED = ['is_active', 'is_featured'];

    public function __invoke(Product $product, string $field, bool $value): Product
    {
        if (! in_array($field, self::ALLOWED, true)) {
            throw new InvalidArgumentException("Field [{$field}] is not toggleable.");
        }

        $original = $product->getOriginal();
        $data     = [$field => $value];

        do_action('product.before_update', $product, $data);
        $product->update($data);
        do_action('product.after_update', $product, $original);
        event(new ProductUpdated($product, $original));

        return $product;
    }
}
