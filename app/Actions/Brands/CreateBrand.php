<?php

namespace App\Actions\Brands;

use App\Events\BrandCreated;
use App\Models\Brand;

/**
 * Persist a new brand.
 *
 * Extension points:
 *   - filter `brand.attributes`        → modify the attribute array
 *   - action `brand.before_create`     → side-effects before insert
 *   - action `brand.after_create`      → side-effects after insert
 *   - event  BrandCreated              → decoupled listeners
 */
class CreateBrand
{
    /** @param  array<string, mixed>  $data  Already-validated payload from BrandRequest. */
    public function __invoke(array $data): Brand
    {
        $data = apply_filters('brand.attributes', $data);
        do_action('brand.before_create', $data);

        $brand = Brand::create($data);

        do_action('brand.after_create', $brand);
        event(new BrandCreated($brand));

        return $brand;
    }
}
