<?php

namespace App\Actions\Roles;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Update a role's name/description and permission set.
 *
 * Guardrails (docs/features/auth-users.md §6.3):
 *   - System roles keep their name + `is_system` flag (name not editable).
 *   - The Admin role can never lose the ability to manage roles/users, so
 *     `roles.manage` + core `users.*` are force-retained on it.
 *
 * Hooks: `role.before_update` · `role.permissions_changed` · `role.after_update`.
 */
class UpdateRole
{
    /** @param array<string,mixed> $data  @param list<int> $permissionIds */
    public function __invoke(Role $role, array $data, array $permissionIds): Role
    {
        if ($role->name === 'Admin') {
            $mustKeep = Permission::query()
                ->whereIn('key', ['roles.manage', 'users.view', 'users.create', 'users.update'])
                ->pluck('id')->all();
            $permissionIds = array_values(array_unique([...$permissionIds, ...$mustKeep]));
        }

        $before = $role->permissions()->pluck('permissions.id')->all();

        do_action('role.before_update', $role, $data);

        $role->update([
            // System role names are immutable — keeps name-based guards stable.
            'name'        => $role->is_system ? $role->name : $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($permissionIds);
        User::bumpPermissionsVersion();

        $added   = array_values(array_diff($permissionIds, $before));
        $removed = array_values(array_diff($before, $permissionIds));
        do_action('role.permissions_changed', $role, $added, $removed);
        do_action('role.after_update', $role);

        return $role;
    }
}
