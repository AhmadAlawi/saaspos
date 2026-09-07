<?php

namespace App\Actions\Products\Concerns;

use App\Models\Product;

/**
 * Frees a SKU / barcode held by a soft-deleted product so a fresh insert (or a
 * rename) doesn't collide with the DB-level unique index.
 *
 * `products.sku` / `products.barcode` are UNIQUE across ALL rows, including
 * soft-deleted ones, but ProductRequest validates uniqueness only among
 * non-trashed rows — deleting a product is meant to free its SKU for reuse.
 * Nothing actually freed the identifier on delete, so reusing a deleted SKU
 * passed validation then hit a raw 1062 at insert. This reclaims it: the
 * trashed row is kept (it may be referenced by stock/purchase history) but its
 * identifiers get a `__del<id>` marker — unique by construction, obviously dead.
 */
trait ReclaimsSoftDeletedIdentifiers
{
    /**
     * @param  int|null  $exceptId  a product id to leave alone (the one being updated)
     */
    protected function reclaimSoftDeletedIdentifiers(?string $sku, ?string $barcode, ?int $exceptId = null): void
    {
        $sku     = ($sku !== null && $sku !== '') ? $sku : null;
        $barcode = ($barcode !== null && $barcode !== '') ? $barcode : null;

        if ($sku === null && $barcode === null) {
            return;
        }

        $trashed = Product::onlyTrashed()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where(function ($q) use ($sku, $barcode) {
                if ($sku !== null) {
                    $q->orWhere('sku', $sku);
                }
                if ($barcode !== null) {
                    $q->orWhere('barcode', $barcode);
                }
            })
            ->get();

        foreach ($trashed as $dead) {
            $suffix = '__del'.$dead->id;
            $dead->forceFill([
                'sku'     => mb_substr((string) $dead->sku, 0, 64 - strlen($suffix)).$suffix,
                'barcode' => $dead->barcode !== null
                    ? mb_substr((string) $dead->barcode, 0, 64 - strlen($suffix)).$suffix
                    : null,
            ])->saveQuietly();

            // A fully-reclaimed dead product has no further use for ITS
            // OWN extra/alternate barcodes (see App\Models\ProductBarcode)
            // either — they'd otherwise keep blocking a fresh product
            // from reusing them the same way the primary sku/barcode did.
            $dead->barcodes()->delete();
        }
    }
}
