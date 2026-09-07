<?php

namespace App\Actions\Roles;

use App\Models\Role;
use App\Models\User;

/**
 * Create a custom role and attach its permissions.
 *
 * Hooks: `role.before_create` ($data) · `role.after_create` ($role).
 * Bumps the permission-cache version so the new role takes effect at once.
 */
class CreateRole
{
    /**
     * @param array<string, mixed> $data           Validated name/description.
     * @param list<int>            $permissionIds   Permission ids to grant.
     */
    public function __invoke(array $data, array $permissionIds): Role
    {
        do_action('role.before_create', $data);

        $role = Role::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_system'   => false,
        ]);

        $role->permissions()->sync($permissionIds);
        User::bumpPermissionsVersion();

        do_action('role.after_create', $role);

        return $role;
    }
}
