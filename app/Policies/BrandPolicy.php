<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

/**
 * Brand authorization — shares `taxonomies.manage` with categories +
 * units (docs/features/auth-users.md §7.4). Super admins bypass via
 * Gate::before.
 */
class BrandPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('taxonomies.manage'); }
    public function view(User $user, Brand $brand): bool { return $user->hasPermission('taxonomies.manage'); }
    public function create(User $user): bool { return $user->hasPermission('taxonomies.manage'); }
    public function update(User $user, Brand $brand): bool { return $user->hasPermission('taxonomies.manage'); }
    public function delete(User $user, Brand $brand): bool { return $user->hasPermission('taxonomies.manage'); }
}
