<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A tokenised, no-login link letting a walk-in customer (no customer
 * attached at checkout) claim an already-completed sale for themselves —
 * see {@see \App\Http\Controllers\SaleClaimController}. Stays valid
 * indefinitely (no expiry) until claimed once; `claimed_at` set makes it
 * permanently inert, same 404-on-reuse behaviour as any other public link
 * here once its purpose is spent.
 */
class SaleClaimLink extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'sale_id',
        'token',
        'claimed_at',
        'claimed_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function claimedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'claimed_customer_id');
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    /**
     * Find the live claim link for a sale, or mint a fresh one. Reusing
     * an existing link keeps the CFD QR stable if the same sale somehow
     * requests it twice before being claimed.
     */
    public static function for(Sale $sale): self
    {
        $existing = static::query()->where('sale_id', $sale->id)->latest('id')->first();
        if ($existing) {
            return $existing;
        }

        return static::create([
            'sale_id'    => $sale->id,
            'token'      => static::freshToken(),
            'created_at' => now(),
        ]);
    }

    /** A URL-safe, collision-checked token (fits the 64-char column). */
    protected static function freshToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }
}
