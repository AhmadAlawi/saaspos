<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cashier shift — the open-to-close session bound to a (store, user)
 * and optionally a terminal. Sales completed during the shift carry
 * `sales.shift_id` so the Z-report can roll them up.
 *
 * Status lifecycle (per `docs/features/cash-drawer-shifts.md` §2):
 *   open → closed
 *        → closed_with_variance
 *
 * Slice 1 wires open + close. Force-close, handover, denomination helper,
 * PDF/thermal Z-report, pay-in/pay-out UI, and per-store enforcement
 * gating ride later slices.
 */
class Shift extends Model
{
    public const STATUS_OPEN                  = 'open';
    public const STATUS_CLOSED                = 'closed';
    public const STATUS_CLOSED_WITH_VARIANCE  = 'closed_with_variance';

    protected $fillable = [
        'store_id', 'terminal_id', 'trading_day_id', 'user_id',
        'opened_at',
        'opening_cash', 'opening_denominations',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'              => 'datetime',
            'closed_at'              => 'datetime',
            'opening_cash'           => 'decimal:4',
            'closing_cash_counted'   => 'decimal:4',
            'expected_cash'          => 'decimal:4',
            'cash_variance'          => 'decimal:4',
            'sales_total'            => 'decimal:4',
            'refunds_total'          => 'decimal:4',
            'opening_denominations'  => 'array',
            'closing_denominations'  => 'array',
            'payment_totals'         => 'array',
        ];
    }

    /* ── Status helpers ─────────────────────────────────────────── */

    public function isOpen(): bool   { return $this->status === self::STATUS_OPEN; }
    public function isClosed(): bool { return in_array($this->status, [self::STATUS_CLOSED, self::STATUS_CLOSED_WITH_VARIANCE], true); }

    /* ── Scopes ─────────────────────────────────────────────────── */

    /** @param Builder<Shift> $q */
    public function scopeOpen(Builder $q): void   { $q->where('status', self::STATUS_OPEN); }

    /** @param Builder<Shift> $q */
    public function scopeForStore(Builder $q, int $storeId): void { $q->where('store_id', $storeId); }

    /** @param Builder<Shift> $q */
    public function scopeForUser(Builder $q, int $userId): void   { $q->where('user_id', $userId); }

    /* ── Relationships ──────────────────────────────────────────── */

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }

    /** @return BelongsTo<TradingDay, $this> */
    public function tradingDay(): BelongsTo { return $this->belongsTo(TradingDay::class); }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Alias — Z-report / index tend to read `cashier` for clarity. */
    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }

    /** @return BelongsTo<User, $this> */
    public function forceCloser(): BelongsTo { return $this->belongsTo(User::class, 'force_closed_by'); }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany { return $this->hasMany(Sale::class); }

    /** @return HasMany<CashDrawerEntry, $this> */
    public function cashDrawerEntries(): HasMany { return $this->hasMany(CashDrawerEntry::class); }

    /* ── Convenience lookups ────────────────────────────────────── */

    /**
     * The open shift this cashier currently owns (if any). Slice 1
     * scopes by (store, user) — Slice 2 will add terminal binding.
     */
    public static function openForCashier(int $storeId, int $userId): ?self
    {
        return self::query()
            ->where('store_id', $storeId)
            ->where('user_id', $userId)
            ->where('status', self::STATUS_OPEN)
            ->latest('opened_at')
            ->first();
    }
}
