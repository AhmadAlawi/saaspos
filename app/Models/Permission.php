<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A single capability in the permission catalog (e.g. `products.update`).
 * The catalog is seeded by {@see Database\Seeders\PermissionsSeeder} per
 * docs/features/auth-users.md §7; adding one is a seeder update.
 *
 * Permissions are grouped by `group` (Sales, Products, …) for the role
 * builder's collapsible matrix, and flagged `is_dangerous` for the
 * separately-confirmed dangerous group.
 */
class Permission extends Model
{
    protected $fillable = ['key', 'label', 'group', 'description', 'is_dangerous'];

    protected function casts(): array
    {
        return ['is_dangerous' => 'boolean'];
    }

    /** @return BelongsToMany<Role> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    /** @param Builder<Permission> $q */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderBy('group')->orderBy('label');
    }
}
