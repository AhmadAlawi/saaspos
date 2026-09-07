<?php

namespace App\Actions\Users;

use App\Actions\Concerns\FreesSoftDeletedUnique;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Create a user record (profile + status + super-admin flag). Store/role
 * assignment is applied separately via {@see SetUserStoreRoles}.
 *
 * Hooks: `user.before_create` ($data) · `user.after_create` ($user).
 */
class CreateUser
{
    use FreesSoftDeletedUnique;

    /**
     * @param array<string, mixed> $data     Validated profile fields.
     * @param string|null          $password Plain password, or null to set a random one (setup-link flow).
     * @param string|null          $pin      Optional 6-digit PIN for the cashier manager-approval numpad.
     */
    public function __invoke(array $data, ?string $password = null, bool $isSuperAdmin = false, ?string $pin = null): User
    {
        do_action('user.before_create', $data);

        // `users.email` is UNIQUE across ALL rows including soft-deleted ones,
        // so a plain insert 1062s when the address belonged to a previously-
        // deleted user (validation ignores trashed rows, the DB index doesn't).
        // Free it off any trashed row first. A live duplicate is already caught
        // by UserRequest's unique rule.
        $this->freeSoftDeletedUnique(User::class, ['email' => $data['email']]);

        $user = new User();
        $user->fill([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'locale'    => $data['locale'] ?? 'en',
            'is_active' => $data['is_active'] ?? true,
        ]);
        // 'hashed' cast hashes on set. A random secret stands in until the
        // user sets one via the setup-link / password-reset flow.
        $user->password        = $password !== null && $password !== '' ? $password : Str::random(40);
        if ($pin !== null && $pin !== '') {
            $user->pin = $pin;
        }
        $user->is_super_admin  = $isSuperAdmin;
        $user->email_verified_at = now();
        $user->save();

        do_action('user.after_create', $user);

        return $user;
    }
}
