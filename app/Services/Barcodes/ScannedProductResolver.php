<?php

namespace App\Services\Barcodes;

use Illuminate\Support\Facades\DB;

/**
 * Resolve a scanned barcode to a single product / variant, in the row shape the
 * inventory editors' `addProduct(row)` pickers consume.
 *
 * Shared by the Stock Adjustment, Transfer, Take and Purchase scan endpoints so
 * the "variant barcode wins over product, exact match only" rule lives in one
 * place. Consumers map only the keys they need, so the shape can grow safely.
 */
class ScannedProductResolver
{
    /**
     * @return array{value:string, label:string, sku:string, product_id:int, variant_id:?int, track_batches:bool, track_expiry:bool}|null
     */
    public function resolve(string $barcode): ?array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        // A variant is the more specific hit, so it wins over a product that
        // happens to share the barcode (mirrors the cashier).
        $variant = DB::table('product_variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->whereNull('p.deleted_at')
            ->whereNull('v.deleted_at')
            ->where('v.barcode', $barcode)
            ->orderBy('v.id')
            ->first(['v.id as variant_id', 'p.id as product_id', 'p.name as product_name',
                     'v.sku as variant_sku', 'v.attributes as variant_attributes',
                     'p.sku as product_sku', 'p.track_batches', 'p.track_expiry']);

        if ($variant) {
            return [
                'value'         => "{$variant->product_id}-{$variant->variant_id}",
                'label'         => "{$variant->product_name} — ".$this->variantLabel($variant->variant_attributes, $variant->variant_sku),
                'sku'           => $variant->variant_sku ?: $variant->product_sku,
                'product_id'    => (int) $variant->product_id,
                'variant_id'    => (int) $variant->variant_id,
                'track_batches' => (bool) $variant->track_batches,
                // The purchase editor reveals the expiry field from this; the
                // inventory editors simply ignore it.
                'track_expiry'  => (bool) $variant->track_expiry,
            ];
        }

        $product = DB::table('products')
            ->whereNull('deleted_at')
            ->where('barcode', $barcode)
            ->orderBy('id')
            ->first(['id', 'name', 'sku', 'track_batches', 'track_expiry']);

        // Falls back to a product's extra/alternate barcodes (see
        // App\Models\ProductBarcode) when the scanned code isn't the
        // product's own primary `barcode`.
        if (! $product) {
            $product = DB::table('product_barcodes as pb')
                ->join('products as p', 'p.id', '=', 'pb.product_id')
                ->whereNull('p.deleted_at')
                ->where('pb.barcode', $barcode)
                ->orderBy('p.id')
                ->first(['p.id', 'p.name', 'p.sku', 'p.track_batches', 'p.track_expiry']);
        }

        if ($product) {
            return [
                'value'         => (string) $product->id,
                'label'         => $product->name,
                'sku'           => $product->sku,
                'product_id'    => (int) $product->id,
                'variant_id'    => null,
                'track_batches' => (bool) $product->track_batches,
                'track_expiry'  => (bool) $product->track_expiry,
            ];
        }

        return null;
    }

    /**
     * Human-readable variant string from the raw `attributes` JSON, mirroring
     * `ProductVariant::label`: prefer `attributes.label`, else join the values,
     * else fall back to the SKU.
     */
    private function variantLabel(?string $attributesJson, ?string $fallbackSku): string
    {
        $sku = (string) ($fallbackSku ?? '');
        if (! $attributesJson) {
            return $sku;
        }
        $attrs = json_decode($attributesJson, true);
        if (! is_array($attrs) || $attrs === []) {
            return $sku;
        }
        if (! empty($attrs['label'])) {
            return (string) $attrs['label'];
        }

        return implode(' · ', array_map('strval', $attrs));
    }
}
