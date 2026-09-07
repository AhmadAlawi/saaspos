<?php

namespace App\Actions\Categories;

use App\Events\CategoryDeleted;
use App\Exceptions\CategoryNotDeletable;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete a category. Children get their parent_id cleared (the FK is
 * `ON DELETE SET NULL` per the migration, but soft-deletes don't fire the
 * referential action, so we reparent explicitly).
 *
 * Products attached to this category:
 *   - if `$replacementId` is given (must be an existing, non-trashed
 *     category, and NOT the row being deleted), every live product is
 *     moved over before the delete.
 *   - otherwise, the system-default "Uncategorized" row is used as the
 *     fallback target. The default row itself is non-deletable.
 *
 * Extension points:
 *   - action `category.before_delete` → fires before soft-delete
 *   - action `category.after_delete`  → fires after soft-delete
 *   - event  CategoryDeleted          → decoupled listeners
 */
class DeleteCategory
{
    public function __invoke(Category $category, ?int $replacementId = null): void
    {
        if ($category->is_default) {
            throw new CategoryNotDeletable('default_protected');
        }

        $target = $this->resolveTarget($category, $replacementId);
        $movedCount = $this->moveProducts($category, $target?->id);

        do_action('category.before_delete', $category);

        $reparentedIds = $category->children()->pluck('id')->all();
        $category->children()->update(['parent_id' => null]);
        $category->delete();

        do_action('category.after_delete', $category, $reparentedIds);
        event(new CategoryDeleted($category, $reparentedIds));

        // Surface the move count for the controller / toast layer.
        $this->movedCount  = $movedCount;
        $this->targetName  = $target?->name;
    }

    /** Public side-effects from the last invocation. */
    public int $movedCount = 0;
    public ?string $targetName = null;

    private function resolveTarget(Category $category, ?int $replacementId): ?Category
    {
        if (! Schema::hasTable('products')) {
            return null;
        }

        if ($replacementId) {
            if ($replacementId === $category->id) {
                throw new CategoryNotDeletable('replacement_is_self');
            }
            $replacement = Category::query()->whereKey($replacementId)->first();
            if (! $replacement) {
                throw new CategoryNotDeletable('replacement_not_found');
            }
            return $replacement;
        }

        // Fall back to the system default — seeded on migrate. If the
        // operator deleted it manually somehow (the policy says they
        // can't, but pre-policy data exists), recreate on demand.
        $default = Category::default();
        if (! $default) {
            $default = Category::query()->create([
                'name'       => 'Uncategorized',
                'slug'       => 'uncategorized',
                'is_active'  => true,
                'is_default' => true,
                'sort_order' => 0,
            ]);
        }
        if ($default->id === $category->id) {
            // Edge: the operator is trying to delete the default itself,
            // which should already have been caught above. Belt-and-braces.
            throw new CategoryNotDeletable('default_protected');
        }
        return $default;
    }

    private function moveProducts(Category $category, ?int $targetId): int
    {
        if (! Schema::hasTable('products') || ! $targetId) {
            return 0;
        }

        return DB::table('products')
            ->where('category_id', $category->id)
            ->whereNull('deleted_at')
            ->update(['category_id' => $targetId, 'updated_at' => now()]);
    }

    /**
     * Count of live products still attached to this category. Kept for
     * the controller to surface "X products will be moved" in the confirm
     * dialog before the destroy fires.
     */
    public static function liveProductCount(Category $category): int
    {
        if (! Schema::hasTable('products')) {
            return 0;
        }
        return DB::table('products')
            ->where('category_id', $category->id)
            ->whereNull('deleted_at')
            ->count();
    }
}
