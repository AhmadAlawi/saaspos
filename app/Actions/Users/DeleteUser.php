<?php

namespace App\Actions\Users;

use App\Exceptions\UserActionDenied;
use App\Models\User;

/**
 * Soft-delete a user. You can't delete yourself, nor the last active
 * super admin. Store memberships are detached so a re-created account
 * starts clean; audit/history FKs are SET NULL per schema.
 *
 * @throws UserActionDenied
 */
class DeleteUser
{
    public function __invoke(User $user, ?int $actingUserId = null): void
    {
        if ($actingUserId !== null && $actingUserId === $user->id) {
            throw new UserActionDenied('self');
        }

        if ($user->is_super_admin && $this->isLastActiveSuperAdmin($user)) {
            throw new UserActionDenied('last_super_admin');
        }

        do_action('user.before_delete', $user);

        $user->stores()->detach();
        $user->delete();
        User::bumpPermissionsVersion();

        do_action('user.after_delete', $user);
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        return User::query()
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->doesntExist();
    }
}
