<?php

namespace App\Http\Controllers\MobileApi;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use Illuminate\Http\Request;

/**
 * Barcode lookup (price checker + label printing) and free-text search
 * for the mobile app. Read-only — safe to query models directly rather
 * than going through the admin Actions layer.
 */
class ProductController
{
    public function lookup(Request $request)
    {
        $barcode = trim((string) $request->query('barcode', ''));
        if ($barcode === '') {
            return response()->json(['message' => 'barcode is required.'], 422);
        }

        $variant = ProductVariant::where('barcode', $barcode)->first();
        $product = $variant?->product ?? Product::where('barcode', $barcode)->first();

        if (! $product) {
            $product = Product::where('sku', $barcode)->first();
        }

        // Extra/alternate barcode (see App\Models\ProductBarcode) — last
        // resort, after the product's own primary barcode and SKU.
        if (! $product) {
            $product = Product::findByAnyBarcode($barcode);
        }

        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $storeId = (int) $request->attributes->get('mobile_api_store_id');

        $stock = StockLevel::query()
            ->where('store_id', $storeId)
            ->where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->value('quantity');

        return response()->json([
            'product_id'     => $product->id,
            'variant_id'     => $variant?->id,
            'name'           => $product->name,
            'sku'            => $variant?->sku ?? $product->sku,
            'barcode'        => $variant?->barcode ?? $product->barcode,
            'selling_price'  => $variant ? $variant->effective_selling_price : $product->selling_price,
            'sale_price'     => $variant ? $variant->effective_sale_price : $product->sale_price,
            'charge_price'   => $variant ? $variant->effective_charge_price : $product->charge_price,
            'stock_quantity' => $stock !== null ? (float) $stock : null,
        ]);
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['message' => 'q must be at least 2 characters.'], 422);
        }

        $products = Product::active()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', 'like', "%{$term}%"));
            })
            ->limit(25)
            ->get(['id', 'name', 'sku', 'barcode', 'selling_price', 'sale_price']);

        return response()->json($products->map(fn (Product $p) => [
            'id'             => $p->id,
            'name'           => $p->name,
            'sku'            => $p->sku,
            'barcode'        => $p->barcode,
            'selling_price'  => $p->selling_price,
            'sale_price'     => $p->sale_price,
            'charge_price'   => $p->charge_price,
        ]));
    }

    /**
     * Cheap "is my offline cache stale?" check — row count + the newest
     * `updated_at` across products/variants/stock levels for this store,
     * so the app can decide whether {@see catalog()} is worth re-downloading
     * without pulling the full payload every time it regains connectivity.
     */
    public function catalogMeta(Request $request)
    {
        $storeId = (int) $request->attributes->get('mobile_api_store_id');

        $productStamp = Product::active()->max('updated_at');
        $variantStamp = ProductVariant::max('updated_at');
        $stockStamp   = StockLevel::where('store_id', $storeId)->max('updated_at');

        $latest = max(array_filter([$productStamp, $variantStamp, $stockStamp]));

        return response()->json([
            'count'      => Product::active()->count(),
            'updated_at' => $latest,
        ]);
    }

    /**
     * Full active catalog for this store — everything the app needs to
     * work offline: price check, label printing, and stock-take against a
     * locally cached list when there's no signal. Flattened (one row per
     * variant, falling back to the parent product for variant-less items)
     * so the client doesn't have to walk a nested shape while scanning.
     */
    public function catalog(Request $request)
    {
        $storeId = (int) $request->attributes->get('mobile_api_store_id');

        $stockByKey = StockLevel::where('store_id', $storeId)
            ->get(['product_id', 'variant_id', 'quantity'])
            ->keyBy(fn ($r) => $r->product_id.':'.($r->variant_id ?? '0'));

        $rows = [];

        Product::active()
            ->with('variants')
            ->select(['id', 'name', 'sku', 'barcode', 'selling_price', 'sale_price', 'updated_at'])
            ->chunk(500, function ($products) use (&$rows, $stockByKey) {
                foreach ($products as $product) {
                    if ($product->variants->isEmpty()) {
                        $stock = $stockByKey->get($product->id.':0');
                        $rows[] = [
                            'product_id'    => $product->id,
                            'variant_id'    => null,
                            'name'          => $product->name,
                            'sku'           => $product->sku,
                            'barcode'       => $product->barcode,
                            'selling_price' => $product->selling_price,
                            'sale_price'    => $product->sale_price,
                            'charge_price'  => $product->charge_price,
                            'stock_quantity' => $stock ? (float) $stock->quantity : null,
                            'updated_at'    => $product->updated_at,
                        ];
                        continue;
                    }

                    foreach ($product->variants as $variant) {
                        $stock = $stockByKey->get($product->id.':'.$variant->id);
                        $rows[] = [
                            'product_id'    => $product->id,
                            'variant_id'    => $variant->id,
                            'name'          => $product->name,
                            'sku'           => $variant->sku ?? $product->sku,
                            'barcode'       => $variant->barcode ?? $product->barcode,
                            'selling_price' => $variant->effective_selling_price,
                            'sale_price'    => $variant->effective_sale_price,
                            'charge_price'  => $variant->effective_charge_price,
                            'stock_quantity' => $stock ? (float) $stock->quantity : null,
                            'updated_at'    => $variant->updated_at,
                        ];
                    }
                }
            });

        return response()->json(['products' => $rows]);
    }
}
