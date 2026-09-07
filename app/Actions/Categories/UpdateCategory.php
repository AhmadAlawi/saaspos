<?php

namespace App\Actions\Categories;

use App\Events\CategoryUpdated;
use App\Models\Category;

/**
 * Update an existing category.
 *
 * Extension points:
 *   - filter `category.attributes`        → modify the attribute array (shared with create)
 *   - action `category.before_update`     → side-effects before save; receives ($category, $data)
 *   - action `category.after_update`      → side-effects after save;  receives ($category, $original)
 *   - event  CategoryUpdated              → decoupled listeners
 */
class UpdateCategory
{
    /**
     * @param  array<string, mixed>  $data  Already-validated payload from CategoryRequest.
     */
    public function __invoke(Category $category, array $data): Category
    {
        $original = $category->getOriginal();

        $data = apply_filters('category.attributes', $data, $category);
        do_action('category.before_update', $category, $data);

        $category->update($data);

        do_action('category.after_update', $category, $original);
        event(new CategoryUpdated($category, $original));

        return $category;
    }
}
