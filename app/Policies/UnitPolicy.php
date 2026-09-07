<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

/**
 * Unit authorization — shares `taxonomies.manage` with categories +
 * brands (docs/features/auth-users.md §7.4). Super admins bypass via
 * Gate::before.
 */
class UnitPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('taxonomies.manage'); }
    public function view(User $user, Unit $unit): bool { return $user->hasPermission('taxonomies.manage'); }
    public function create(User $user): bool { return $user->hasPermission('taxonomies.manage'); }
    public function update(User $user, Unit $unit): bool { return $user->hasPermission('taxonomies.manage'); }
    public function delete(User $user, Unit $unit): bool { return $user->hasPermission('taxonomies.manage'); }
}
