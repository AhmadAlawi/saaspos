<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A tokenised, no-login public link to a customer-facing document. The
 * token is opaque and random (not guessable / enumerable); the referenced
 * document is resolved polymorphically so this one model backs the CFD
 * receipt QR, WhatsApp receipts, and email/SMS receipts alike.
 *
 * See docs/features/whatsapp-receipts.md §5 and database-schema.md §17.2.
 */
class ReceiptPublicLink extends Model
{
    /** Only a created_at is tracked (links are immutable once minted). */
    public const UPDATED_AT = null;

    /** Default link lifetime, in days. Rotatable / per-store-configurable later. */
    public const DEFAULT_EXPIRY_DAYS = 90;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'token',
        'expires_at',
        'views',
        'last_viewed_at',
        'rotated_from_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'     => 'datetime',
            'last_viewed_at' => 'datetime',
            'views'          => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /** True once the link has passed its expiry (a null expiry never expires). */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Live (unexpired) links only. */
    public function scopeActive(Builder $q): void
    {
        $q->where(function (Builder $w) {
            $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Find the live public link for a document, or mint a fresh one. Reusing
     * the existing link keeps a document's URL stable across reprints /
     * re-sends; a new one is created only when none is live.
     */
    public static function for(Model $reference, ?int $expiryDays = null): self
    {
        $existing = static::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->active()
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $days = $expiryDays ?? self::DEFAULT_EXPIRY_DAYS;

        return static::create([
            'reference_type' => $reference->getMorphClass(),
            'reference_id'   => $reference->getKey(),
            'token'          => static::freshToken(),
            'expires_at'     => $days > 0 ? now()->addDays($days) : null,
            'views'          => 0,
            'created_at'     => now(),
        ]);
    }

    /** Record a view — bumps the counter and stamps last_viewed_at. */
    public function markViewed(): void
    {
        $this->forceFill([
            'views'          => $this->views + 1,
            'last_viewed_at' => now(),
        ])->save();
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
