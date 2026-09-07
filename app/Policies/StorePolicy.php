<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

/**
 * Store authorization — backed by the permission catalog
 * (docs/features/auth-users.md §7.14). Super admins bypass via
 * AuthServiceProvider's Gate::before. Switching the active store is
 * gated separately — see StoreSwitchController (access, not permission).
 */
class StorePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('stores.view'); }

    public function view(User $user, Store $store): bool { return $user->hasPermission('stores.view'); }

    public function create(User $user): bool { return $user->hasPermission('stores.create'); }

    public function update(User $user, Store $store): bool { return $user->hasPermission('stores.update'); }

    public function delete(User $user, Store $store): bool { return $user->hasPermission('stores.delete'); }
}
