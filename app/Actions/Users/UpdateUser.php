<?php

namespace App\Actions\Users;

use App\Exceptions\UserActionDenied;
use App\Models\User;

/**
 * Update a user's profile + status, optionally their password (blank =
 * unchanged) and super-admin flag. Demoting the last active super admin
 * is refused. Store/role assignment is handled separately.
 *
 * Hooks: `user.before_update` ($user, $data) · `user.after_update` ($user).
 *
 * @throws UserActionDenied
 */
class UpdateUser
{
    /** @param array<string, mixed> $data */
    public function __invoke(User $user, array $data, ?string $password = null, ?bool $isSuperAdmin = null, ?string $pin = null): User
    {
        if ($isSuperAdmin === false && $user->is_super_admin && $this->isLastActiveSuperAdmin($user)) {
            throw new UserActionDenied('last_super_admin');
        }

        do_action('user.before_update', $user, $data);

        $user->fill([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'locale'    => $data['locale'] ?? $user->locale,
            'is_active' => $data['is_active'] ?? $user->is_active,
        ]);

        if ($password !== null && $password !== '') {
            $user->password = $password;
        }

        if ($pin !== null && $pin !== '') {
            $user->pin = $pin;
        }

        if ($isSuperAdmin !== null) {
            $user->is_super_admin = $isSuperAdmin;
        }

        $user->save();

        do_action('user.after_update', $user);

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
