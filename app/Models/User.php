<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasPermissions;
use App\Models\Concerns\MasksDemoEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'pin', 'phone', 'locale', 'avatar_path', 'is_active', 'default_store_id'])]
#[Hidden(['password', 'pin', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPermissions, MasksDemoEmail, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            // 6-digit PIN used only for the cashier's touchscreen manager-
            // approval numpad (discount/refund) — never for normal login.
            'pin'               => 'hashed',
            'is_super_admin'    => 'boolean',
            'is_active'         => 'boolean',
            'preferences'       => 'array',
            'last_login_at'     => 'datetime',
        ];
    }

    /**
     * Two-letter avatar initials derived from the user's name.
     * First letter of each of the first two words, uppercased.
     * Falls back to the first two letters of the single word, or 'U' if empty.
     */
    public function getInitialsAttribute(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return 'U';
        }

        $parts  = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first  = mb_substr($parts[0] ?? '', 0, 1);
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : mb_substr($parts[0] ?? '', 1, 1);

        return mb_strtoupper($first . $second) ?: 'U';
    }

    /** Public URL for the user's avatar, or null when none is set. */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::url($this->avatar_path) : null;
    }

    /**
     * Stores this user belongs to, each with the role they hold there
     * (roles can differ per store — see docs/features/multi-store.md §1).
     *
     * @return BelongsToMany<Store>
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    /** @return BelongsTo<Store, User> */
    public function defaultStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    /**
     * Stores this user may operate against. Super admins reach every
     * active store; everyone else is limited to their `store_user`
     * memberships.
     *
     * @return \Illuminate\Support\Collection<int, Store>
     */
    public function accessibleStores(): \Illuminate\Support\Collection
    {
        if ($this->is_super_admin) {
            return Store::query()->active()->ordered()->get();
        }

        return $this->stores()->where('is_active', true)->orderBy('name')->get();
    }

    public function canAccessStore(int $storeId): bool
    {
        if ($this->is_super_admin) {
            return Store::query()->active()->whereKey($storeId)->exists();
        }

        return $this->stores()->where('stores.id', $storeId)->where('is_active', true)->exists();
    }

    /**
     * IDs of every store this user can reach. Super admins get all active
     * stores; everyone else gets their assigned stores. Used to hard-scope
     * list/report screens so a restricted user never sees another store's
     * data (see {@see enforce_store_access()}).
     *
     * @return array<int, int>
     */
    public function accessibleStoreIds(): array
    {
        return $this->accessibleStores()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
