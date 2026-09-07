<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending charge tied to a cashier's ring-up. Customer scans the
 * QR pointing at this row's public UUID; their gateway pick lands
 * back here as `payment_method_id` + `gateway_*` after the round-trip.
 *
 * Status flow: pending → selected → paid (success path).
 *              Or: pending|selected → failed|expired|cancelled.
 */
class PosPaymentSession extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_SELECTED  = 'selected';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /** Default time a session stays scan-able before we mark it expired. */
    public const DEFAULT_TTL_MINUTES = 15;

    protected $fillable = [
        'uuid', 'store_id', 'cashier_id', 'local_uuid',
        'amount', 'currency', 'cart_summary',
        'status', 'payment_method_id', 'allowed_method_ids',
        'gateway_session_id', 'gateway_payment_id', 'wallet_brand',
        'failure_message',
        'expires_at', 'paid_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'             => 'decimal:4',
            'cart_summary'       => 'array',
            'allowed_method_ids' => 'array',
            'expires_at'         => 'datetime',
            'paid_at'            => 'datetime',
            'cancelled_at'       => 'datetime',
        ];
    }

    /**
     * Payment-method ids the customer chooser may offer for this session.
     * NULL / empty = unrestricted (the cashier's generic QR advertises every
     * configured gateway). A kiosk session stamps its terminal's allow-list
     * here so the shopper only sees what the merchant enabled for that
     * station. See docs/features/kiosk-self-ordering.md §5.
     *
     * @return array<int, int>
     */
    public function allowedMethodIds(): array
    {
        return collect($this->allowed_method_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    /** True when `$methodId` may be used to pay this session. */
    public function allowsMethod(int $methodId): bool
    {
        $allowed = $this->allowedMethodIds();

        return $allowed === [] || \in_array($methodId, $allowed, true);
    }

    public function isPending(): bool
    {
        return \in_array($this->status, [self::STATUS_PENDING, self::STATUS_SELECTED], true);
    }

    public function isPaid(): bool      { return $this->status === self::STATUS_PAID; }
    public function isTerminal(): bool
    {
        return \in_array($this->status, [
            self::STATUS_PAID, self::STATUS_FAILED, self::STATUS_EXPIRED, self::STATUS_CANCELLED,
        ], true);
    }

    /** Has the TTL elapsed? Independent of the persisted status flag. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast()
            && ! \in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED], true);
    }

    /** @param Builder<PosPaymentSession> $q */
    public function scopePending(Builder $q): void
    {
        $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_SELECTED]);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
