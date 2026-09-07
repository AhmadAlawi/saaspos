<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

/**
 * Supplier authorization. Permissions are seeded by PermissionsSeeder:
 * `suppliers.view`, `.create`, `.update`, `.delete`, `.import`,
 * `.export`. Super admins bypass via Gate::before.
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('suppliers.view'); }
    public function view(User $user, Supplier $s): bool   { return $user->hasPermission('suppliers.view'); }
    public function create(User $user): bool  { return $user->hasPermission('suppliers.create'); }
    public function update(User $user, Supplier $s): bool { return $user->hasPermission('suppliers.update'); }
    public function delete(User $user, Supplier $s): bool { return $user->hasPermission('suppliers.delete'); }
    public function import(User $user): bool  { return $user->hasPermission('suppliers.import'); }
    public function export(User $user): bool  { return $user->hasPermission('suppliers.export'); }
}
