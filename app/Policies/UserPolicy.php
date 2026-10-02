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
        if ($user->id === $target->id) {
            return true;
        }

        // A super admin bypasses this whole policy via Gate::before, so
        // getting here means $user is definitely NOT one — a manager
        // (or anyone else short of super admin) may never edit a
        // super-admin account or a fellow manager-tier account, no
        // matter what flat permission they hold. `users.update` alone
        // used to be sufficient, which let a Manager change the
        // super admin's password or another Manager's.
        if ($target->is_super_admin || $target->hasManagerTierRole()) {
            return false;
        }

        return $user->hasPermission('users.update');
    }

    public function delete(User $user, User $target): bool
    {
        // Never delete yourself. Same manager-tier protection as
        // update() — a Manager may not delete a super admin or a peer.
        if ($user->id === $target->id) {
            return false;
        }
        if ($target->is_super_admin || $target->hasManagerTierRole()) {
            return false;
        }

        return $user->hasPermission('users.delete');
    }

    /** Granting/removing the super-admin flag is a dangerous action. */
    public function manageSuperAdmin(User $user): bool
    {
        return $user->hasPermission('users.create_super_admin');
    }

    /**
     * Password reset, role assignment, and store-access changes on the
     * user edit form are super-admin-only — `users.update` no longer
     * implies any of the three, for ANY target (not just super-admin/
     * manager-tier ones covered by update()'s own check). A Manager
     * holding `users.update` could otherwise reset a cashier's password,
     * hand them a different role, or grant/revoke their store access.
     * Self-service password changes go through ProfileController
     * instead, so this is a hard "no" with no permission escape hatch —
     * Gate::before already lets a real super admin through before this
     * ever runs.
     */
    public function manageAccessControl(User $user): bool
    {
        return false;
    }
}
