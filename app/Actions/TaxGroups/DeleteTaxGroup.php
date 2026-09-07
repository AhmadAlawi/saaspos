<?php

namespace App\Actions\TaxGroups;

use App\Exceptions\TaxGroupHasProducts;
use App\Models\TaxGroup;
use Illuminate\Support\Facades\DB;

/**
 * Delete a tax group with optional move-to-target semantics — same shape
 * as DeleteCategory / DeleteBrand.
 *
 * Two paths:
 *   1. `replacementId` provided → reassign every referencing product
 *      AND category to that group, then delete the original. Atomic.
 *   2. `replacementId` null → only deletes if nothing references the
 *      group; otherwise throws TaxGroupHasProducts so the controller
 *      can surface the move-products picker.
 *
 * The default group cannot be deleted under either path — the system
 * always needs a fallback for `is_default`.
 */
class DeleteTaxGroup
{
    /** Combined products + categories reassigned during this delete. */
    public int $movedCount = 0;

    /** Display name of the target group when a move happened, else ''. */
    public string $targetName = '';

    public function __invoke(TaxGroup $group, ?int $replacementId = null): void
    {
        if ($group->is_default) {
            // System default — uncategorised products' fallback. Never
            // deletable, even with a replacement, so reports always have
            // a sane bucket to put orphans in.
            throw new TaxGroupHasProducts(
                productCount:  $group->products()->count(),
                categoryCount: $group->categories()->count(),
            );
        }

        DB::transaction(function () use ($group, $replacementId) {
            // Live (non-soft-deleted) referrer counts. Mirror DeleteCategory's
            // DB::table approach — avoids any Eloquent global scopes that
            // could quietly filter out rows the FK cascade would still hit.
            $productCount  = (int) DB::table('products')
                ->where('tax_group_id', $group->id)
                ->whereNull('deleted_at')
                ->count();
            $categoryCount = (int) DB::table('categories')
                ->where('tax_group_id', $group->id)
                ->whereNull('deleted_at')
                ->count();

            if ($replacementId !== null && $replacementId !== $group->id) {
                $target = TaxGroup::query()->whereKey($replacementId)->firstOrFail();

                DB::table('products')
                    ->where('tax_group_id', $group->id)
                    ->whereNull('deleted_at')
                    ->update(['tax_group_id' => $target->id, 'updated_at' => now()]);

                DB::table('categories')
                    ->where('tax_group_id', $group->id)
                    ->whereNull('deleted_at')
                    ->update(['tax_group_id' => $target->id, 'updated_at' => now()]);

                $this->movedCount = $productCount + $categoryCount;
                $this->targetName = (string) $target->name;
            } elseif ($productCount > 0 || $categoryCount > 0) {
                throw new TaxGroupHasProducts($productCount, $categoryCount);
            }

            do_action('tax_group.before_delete', $group);
            $group->components()->detach();
            $group->delete();
            do_action('tax_group.after_delete', $group);
        });
    }

    /** Lightweight count for the delete dialog pre-flight. */
    public static function liveReferrerCount(TaxGroup $group): int
    {
        $p = (int) DB::table('products')->where('tax_group_id', $group->id)->whereNull('deleted_at')->count();
        $c = (int) DB::table('categories')->where('tax_group_id', $group->id)->whereNull('deleted_at')->count();
        return $p + $c;
    }
}
