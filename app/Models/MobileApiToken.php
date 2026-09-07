<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Bearer token issued to the Expo mobile app. Plaintext token is shown to
 * the client exactly once at login and never stored — only its hash is
 * persisted, checked the same way password hashes are.
 */
class MobileApiToken extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'token',
        'name',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Issue a new token for the given user/store, returning the ONE-TIME
     * plaintext value. Caller is responsible for handing it to the client
     * and never logging/storing it anywhere else.
     */
    public static function issue(User $user, int $storeId, ?string $name = null): string
    {
        $plaintext = Str::random(64);

        static::create([
            'user_id'  => $user->id,
            'store_id' => $storeId,
            'token'    => hash('sha256', $plaintext),
            'name'     => $name,
            'expires_at' => now()->addDays(90),
        ]);

        return $plaintext;
    }

    public static function resolve(string $plaintext): ?self
    {
        return static::query()
            ->where('token', hash('sha256', $plaintext))
            ->first();
    }
}
