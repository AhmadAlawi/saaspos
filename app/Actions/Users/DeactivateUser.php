<?php

namespace App\Actions\Users;

use App\Exceptions\UserActionDenied;
use App\Models\User;

/**
 * Flip a user's active flag. You can't deactivate yourself, and the last
 * active super admin can't be deactivated (there must always be a way in).
 *
 * @throws UserActionDenied
 */
class DeactivateUser
{
    public function __invoke(User $user, bool $active, ?int $actingUserId = null): User
    {
        if (! $active) {
            if ($actingUserId !== null && $actingUserId === $user->id) {
                throw new UserActionDenied('self');
            }
            if ($user->is_super_admin && $this->isLastActiveSuperAdmin($user)) {
                throw new UserActionDenied('last_super_admin');
            }
        }

        $user->forceFill(['is_active' => $active])->save();

        do_action($active ? 'user.activated' : 'user.deactivated', $user);

        return $user;
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
