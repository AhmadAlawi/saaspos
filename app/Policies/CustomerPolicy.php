<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * Customer authorization. Permissions are seeded by PermissionsSeeder:
 * `customers.view`, `.create`, `.update`, `.delete`, `.import`,
 * `.export`. Super admins bypass via Gate::before.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool   { return $user->hasPermission('customers.view'); }
    public function view(User $user, Customer $c): bool   { return $user->hasPermission('customers.view'); }
    public function create(User $user): bool    { return $user->hasPermission('customers.create'); }
    public function update(User $user, Customer $c): bool { return $user->hasPermission('customers.update'); }
    public function delete(User $user, Customer $c): bool { return $user->hasPermission('customers.delete'); }
    public function import(User $user): bool    { return $user->hasPermission('customers.import'); }
    public function export(User $user): bool    { return $user->hasPermission('customers.export'); }
}
