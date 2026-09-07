<?php

namespace App\Actions\Categories;

use App\Events\CategoryCreated;
use App\Models\Category;

/**
 * Persist a new category.
 *
 * Extension points:
 *   - filter `category.attributes`        → modify the attribute array
 *   - action `category.before_create`     → side-effects before insert
 *   - action `category.after_create`      → side-effects after insert
 *   - event  CategoryCreated              → decoupled listeners
 */
class CreateCategory
{
    /**
     * @param  array<string, mixed>  $data  Already-validated payload from CategoryRequest.
     */
    public function __invoke(array $data): Category
    {
        $data['sort_order'] ??= (Category::max('sort_order') ?? 0) + 1;

        $data = apply_filters('category.attributes', $data);
        do_action('category.before_create', $data);

        $category = Category::create($data);

        do_action('category.after_create', $category);
        event(new CategoryCreated($category));

        return $category;
    }
}
