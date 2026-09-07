<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Product authorization — backed by the permission catalog
 * (docs/features/auth-users.md §7.3). Super admins bypass via
 * AuthServiceProvider's Gate::before.
 */
class ProductPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('products.view'); }
    public function view(User $user, Product $product): bool { return $user->hasPermission('products.view'); }
    public function create(User $user): bool { return $user->hasPermission('products.create'); }
    public function update(User $user, Product $product): bool { return $user->hasPermission('products.update'); }
    public function delete(User $user, Product $product): bool { return $user->hasPermission('products.delete'); }
}
