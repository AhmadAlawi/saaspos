<?php

namespace App\Actions\Users;

use App\Models\User;

/**
 * Sync a user's store memberships + their role per store (the
 * `store_user` pivot). Keeps `default_store_id` valid — pointed at the
 * first assigned store when the current default is unset or no longer
 * granted. Bumps the permission cache so changes take effect at once.
 */
class SetUserStoreRoles
{
    /** @param array<int, int> $storeRoles  store_id => role_id */
    public function __invoke(User $user, array $storeRoles): void
    {
        $sync = [];
        foreach ($storeRoles as $storeId => $roleId) {
            $sync[(int) $storeId] = ['role_id' => (int) $roleId];
        }

        $user->stores()->sync($sync);

        $storeIds = array_keys($sync);
        if (! $user->default_store_id || ! in_array((int) $user->default_store_id, $storeIds, true)) {
            $user->forceFill(['default_store_id' => $storeIds[0] ?? null])->save();
        }

        User::bumpPermissionsVersion();
    }
}
