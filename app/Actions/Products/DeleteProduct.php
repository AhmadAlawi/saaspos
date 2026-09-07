<?php

namespace App\Actions\Products;

use App\Events\ProductDeleted;
use App\Exceptions\ProductHasSales;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete a product. Refuses to delete if the product appears on
 * any historical sale — removing it would orphan audit and reporting
 * lines. Surfaces as {@see ProductHasSales}, which the controller
 * converts to a friendly 422 response.
 *
 * Extension points:
 *   - action `product.before_delete`  → fires before soft-delete
 *   - action `product.after_delete`   → fires after soft-delete
 *   - event  ProductDeleted           → decoupled listeners
 */
class DeleteProduct
{
    public function __invoke(Product $product): void
    {
        $this->assertNoLiveSales($product);

        do_action('product.before_delete', $product);

        $product->delete();

        do_action('product.after_delete', $product);
        event(new ProductDeleted($product));
    }

    /**
     * Block the delete when this product (or ANY of its variants) is
     * referenced from a sale_items row. Removing the parent would
     * cascade-orphan its children which are themselves on sale lines.
     *
     * Guarded against fresh installs where sale_items / product_variants
     * haven't been migrated yet.
     */
    private function assertNoLiveSales(Product $product): void
    {
        if (! Schema::hasTable('sale_items')) {
            return;
        }

        $count = DB::table('sale_items')
            ->where('product_id', $product->id)
            ->count();

        // Variant lines reference variants directly via `variant_id`;
        // even if `product_id` doesn't match (it's set per-line by the
        // cashier and may be left null for variant rows), join across
        // to catch them.
        if (Schema::hasTable('product_variants')) {
            $count += DB::table('sale_items')
                ->join('product_variants', 'sale_items.variant_id', '=', 'product_variants.id')
                ->where('product_variants.product_id', $product->id)
                ->count();
        }

        if ($count > 0) {
            throw new ProductHasSales($count);
        }
    }
}
