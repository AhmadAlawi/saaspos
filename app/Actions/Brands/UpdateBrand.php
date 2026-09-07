<?php

namespace App\Actions\Brands;

use App\Events\BrandUpdated;
use App\Models\Brand;

/**
 * Update an existing brand.
 *
 * Extension points:
 *   - filter `brand.attributes`        → modify the attribute array (shared with create)
 *   - action `brand.before_update`     → side-effects before save; receives ($brand, $data)
 *   - action `brand.after_update`      → side-effects after save;  receives ($brand, $original)
 *   - event  BrandUpdated              → decoupled listeners
 */
class UpdateBrand
{
    /** @param  array<string, mixed>  $data  Already-validated payload from BrandRequest. */
    public function __invoke(Brand $brand, array $data): Brand
    {
        $original = $brand->getOriginal();

        $data = apply_filters('brand.attributes', $data, $brand);
        do_action('brand.before_update', $brand, $data);

        $brand->update($data);

        do_action('brand.after_update', $brand, $original);
        event(new BrandUpdated($brand, $original));

        return $brand;
    }
}
