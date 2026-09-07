<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named set of permissions. Five system roles (Admin, Manager,
 * Cashier, Stock Keeper, Accountant) ship seeded with `is_system = 1`;
 * they can be edited but never deleted. Users hold a role *per store*
 * via the `store_user` pivot.
 */
class Role extends Model
{
    protected $fillable = ['name', 'description', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /** @return BelongsToMany<Permission> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Users assigned this role in any store (via the store_user pivot).
     *
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot('store_id')
            ->withTimestamps();
    }

    /** Number of store-assignments using this role — gates deletion. */
    public function assignmentCount(): int
    {
        return \Illuminate\Support\Facades\DB::table('store_user')
            ->where('role_id', $this->id)
            ->count();
    }

    /** @param Builder<Role> $q */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderByDesc('is_system')->orderBy('name');
    }
}
