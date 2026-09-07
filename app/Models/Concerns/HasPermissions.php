<?php

namespace App\Models\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Permission resolution for {@see App\Models\User}.
 *
 * A user holds a role *per store* (the `store_user` pivot). Their
 * effective permissions for a store are that role's permission keys.
 * Super admins implicitly have everything (and bypass the gate via
 * AuthServiceProvider's Gate::before).
 *
 * Caching is shared-hosting friendly (no cache tags): each lookup is
 * keyed by a global version counter, so any role/permission change just
 * bumps the version (see {@see bumpPermissionsVersion()}) and every
 * stale key becomes unreachable at once — no per-key invalidation.
 */
trait HasPermissions
{
    /**
     * Effective permission keys for the given store (defaults to the
     * active store). Empty when the user has no role in that store.
     *
     * @return list<string>
     */
    public function effectivePermissions(?int $storeId = null): array
    {
        if ($this->is_super_admin) {
            return Permission::query()->pluck('key')->all();
        }

        $storeId ??= current_store_id();
        if (! $storeId) {
            return [];
        }

        $version = static::permissionsVersion();

        $keys = Cache::remember(
            "perms:v{$version}:u{$this->id}:s{$storeId}",
            now()->addMinutes(5),
            fn () => $this->permissionKeysForStore($storeId),
        );

        return apply_filters('user.permissions', $keys, $this, $storeId);
    }

    /** @return list<string> */
    protected function permissionKeysForStore(int $storeId): array
    {
        $roleId = DB::table('store_user')
            ->where('user_id', $this->id)
            ->where('store_id', $storeId)
            ->value('role_id');

        if (! $roleId) {
            return [];
        }

        return DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $roleId)
            ->pluck('permissions.key')
            ->all();
    }

    /** Does the user hold `$key` in the given (or active) store? */
    public function hasPermission(string $key, ?int $storeId = null): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return in_array($key, $this->effectivePermissions($storeId), true);
    }

    /** True only if the user holds *every* listed permission. */
    public function hasAllPermissions(array $keys, ?int $storeId = null): bool
    {
        foreach ($keys as $key) {
            if (! $this->hasPermission($key, $storeId)) {
                return false;
            }
        }

        return true;
    }

    /** True if the user holds `$key` in ANY store they belong to. */
    public function hasAnyPermissionAcrossStores(string $key): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $roleIds = DB::table('store_user')->where('user_id', $this->id)->pluck('role_id')->unique();
        if ($roleIds->isEmpty()) {
            return false;
        }

        return DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->whereIn('role_permission.role_id', $roleIds)
            ->where('permissions.key', $key)
            ->exists();
    }

    /** The role this user holds in the given (or active) store. */
    public function roleForStore(?int $storeId = null): ?Role
    {
        $storeId ??= current_store_id();
        if (! $storeId) {
            return null;
        }

        $roleId = DB::table('store_user')
            ->where('user_id', $this->id)
            ->where('store_id', $storeId)
            ->value('role_id');

        return $roleId ? Role::find($roleId) : null;
    }

    public static function permissionsVersion(): int
    {
        return (int) Cache::get('perms.version', 1);
    }

    /** Invalidate every cached permission set (call on role/assignment change). */
    public static function bumpPermissionsVersion(): void
    {
        Cache::forever('perms.version', static::permissionsVersion() + 1);
    }
}
