<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

/**
 * Purchase authorization. Permissions are seeded by PermissionsSeeder:
 * `purchases.view`, `.create`, `.update`, `.delete`, `.receive`.
 * Super admins bypass via Gate::before.
 */
class PurchasePolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('purchases.view'); }
    public function view(User $user, Purchase $p): bool   { return $user->hasPermission('purchases.view'); }
    public function create(User $user): bool  { return $user->hasPermission('purchases.create'); }
    public function update(User $user, Purchase $p): bool { return $user->hasPermission('purchases.update'); }
    public function delete(User $user, Purchase $p): bool { return $user->hasPermission('purchases.delete'); }
    public function receive(User $user, Purchase $p): bool { return $user->hasPermission('purchases.receive'); }
}
