<?php

namespace App\Policies;

use App\Models\User;

/**
 * User authorization (docs/features/auth-users.md §7.13). Super admins
 * bypass via Gate::before. Users may always view/update their own
 * profile regardless of permission (self-service).
 */
class UserPolicy
{
    public function viewAny(User $user): bool { return $user->hasPermission('users.view'); }

    public function view(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->hasPermission('users.view');
    }

    public function create(User $user): bool { return $user->hasPermission('users.create'); }

    public function update(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->hasPermission('users.update');
    }

    public function delete(User $user, User $target): bool
    {
        // Never delete yourself; otherwise needs the permission.
        return $user->id !== $target->id && $user->hasPermission('users.delete');
    }

    /** Granting/removing the super-admin flag is a dangerous action. */
    public function manageSuperAdmin(User $user): bool
    {
        return $user->hasPermission('users.create_super_admin');
    }
}
