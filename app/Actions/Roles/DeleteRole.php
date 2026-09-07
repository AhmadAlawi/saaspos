<?php

namespace App\Actions\Roles;

use App\Exceptions\RoleNotDeletable;
use App\Models\Role;
use App\Models\User;

/**
 * Delete a role. Any role can be deleted — including the built-in system
 * roles — EXCEPT one that's still assigned to users: that must be
 * reassigned first (otherwise we'd orphan those users' access). The
 * `store_user.role_id` FK is `restrictOnDelete`, so this guard also
 * mirrors the database constraint with a friendly message.
 *
 * @throws RoleNotDeletable
 */
class DeleteRole
{
    public function __invoke(Role $role): void
    {
        $assignments = $role->assignmentCount();
        if ($assignments > 0) {
            throw new RoleNotDeletable('assigned', $assignments);
        }

        do_action('role.before_delete', $role);

        $role->permissions()->detach();
        $role->delete();
        User::bumpPermissionsVersion();

        do_action('role.deleted', $role);
    }
}
