<?php

namespace App\Actions\Brands;

use App\Events\BrandDeleted;
use App\Exceptions\BrandNotDeletable;
use App\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete a brand. Products attached:
 *   - if `$replacementId` is given, every live product is moved there
 *     before the delete (excludes the row being deleted).
 *   - otherwise, the system-default "Generic" brand is used as the
 *     fallback target. The default row itself is non-deletable.
 *
 * Extension points:
 *   - action `brand.before_delete`     → fires before soft-delete
 *   - action `brand.after_delete`      → fires after soft-delete
 *   - event  BrandDeleted              → decoupled listeners
 */
class DeleteBrand
{
    public int $movedCount = 0;
    public ?string $targetName = null;

    public function __invoke(Brand $brand, ?int $replacementId = null): void
    {
        if ($brand->is_default) {
            throw new BrandNotDeletable('default_protected');
        }

        $target = $this->resolveTarget($brand, $replacementId);
        $this->movedCount = $this->moveProducts($brand, $target?->id);
        $this->targetName = $target?->name;

        do_action('brand.before_delete', $brand);

        $brand->delete();

        do_action('brand.after_delete', $brand);
        event(new BrandDeleted($brand));
    }

    private function resolveTarget(Brand $brand, ?int $replacementId): ?Brand
    {
        if (! Schema::hasTable('products')) {
            return null;
        }

        if ($replacementId) {
            if ($replacementId === $brand->id) {
                throw new BrandNotDeletable('replacement_is_self');
            }
            $replacement = Brand::query()->whereKey($replacementId)->first();
            if (! $replacement) {
                throw new BrandNotDeletable('replacement_not_found');
            }
            return $replacement;
        }

        $default = Brand::default();
        if (! $default) {
            $default = Brand::query()->create([
                'name'       => 'Generic',
                'slug'       => 'generic',
                'is_active'  => true,
                'is_default' => true,
            ]);
        }
        if ($default->id === $brand->id) {
            throw new BrandNotDeletable('default_protected');
        }
        return $default;
    }

    private function moveProducts(Brand $brand, ?int $targetId): int
    {
        if (! Schema::hasTable('products') || ! $targetId) {
            return 0;
        }

        return DB::table('products')
            ->where('brand_id', $brand->id)
            ->whereNull('deleted_at')
            ->update(['brand_id' => $targetId, 'updated_at' => now()]);
    }

    public static function liveProductCount(Brand $brand): int
    {
        if (! Schema::hasTable('products')) {
            return 0;
        }
        return DB::table('products')
            ->where('brand_id', $brand->id)
            ->whereNull('deleted_at')
            ->count();
    }
}
