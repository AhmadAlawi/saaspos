<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Category authorization. Categories, brands, and units share the
 * single low-risk `taxonomies.manage` permission (docs/features/
 * auth-users.md §7.4). Super admins bypass via Gate::before.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('taxonomies.manage'); }

    public function view(User $user, Category $category): bool { return $user->hasPermission('taxonomies.manage'); }

    public function create(User $user): bool { return $user->hasPermission('taxonomies.manage'); }

    public function update(User $user, Category $category): bool { return $user->hasPermission('taxonomies.manage'); }

    public function delete(User $user, Category $category): bool { return $user->hasPermission('taxonomies.manage'); }

    /** Drag-to-reorder is a bulk update — guard it the same as `update`. */
    public function reorder(User $user): bool { return $user->hasPermission('taxonomies.manage'); }
}
