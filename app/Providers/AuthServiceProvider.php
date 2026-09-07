<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the permission system into Laravel's Gate.
 *
 *  - Super admins bypass every gate (Gate::before).
 *  - Each catalog permission key becomes a gate, so both
 *    `$this->authorize('products.update')` and `@can('products.update')`
 *    resolve against the user's effective permissions.
 *
 * Model-ability authorization (`authorize('viewAny', Product::class)`)
 * keeps flowing through the auto-discovered *Policy classes, which call
 * `$user->hasPermission(...)` internally.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(fn (User $user) => $user->is_super_admin ? true : null);

        foreach ($this->permissionKeys() as $key) {
            Gate::define($key, fn (User $user) => $user->hasPermission($key));
        }
    }

    /**
     * Catalog permission keys, cached and busted by the same version
     * counter the per-user permission cache uses. Returns an empty list
     * before the table exists (fresh install / mid-migration) so boot
     * never crashes.
     *
     * @return list<string>
     */
    private function permissionKeys(): array
    {
        try {
            if (! Schema::hasTable('permissions')) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return Cache::remember(
            'perms.keys.v'.User::permissionsVersion(),
            now()->addMinutes(30),
            fn () => Permission::query()->pluck('key')->all(),
        );
    }
}
