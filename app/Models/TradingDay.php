<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wraps every {@see Shift} opened on one terminal on one calendar date.
 * Each employee still opens/closes their own Shift with their own cash
 * count — this is purely a rollup so the business gets one combined
 * report at end of day instead of one per staff handover.
 *
 * Lifecycle: open (created by the first shift of the day) → closed
 * (explicit "Close Day" action, only once every child shift is closed).
 */
class TradingDay extends Model
{
    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'store_id', 'terminal_id', 'business_date',
        'status', 'opened_at', 'opened_by',
        'closed_at', 'closed_by', 'report_path',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opened_at'      => 'datetime',
            'closed_at'      => 'datetime',
        ];
    }

    public function isOpen(): bool { return $this->status === self::STATUS_OPEN; }

    /** @param Builder<TradingDay> $q */
    public function scopeOpen(Builder $q): void { $q->where('status', self::STATUS_OPEN); }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }

    /** @return BelongsTo<Terminal, $this> */
    public function terminal(): BelongsTo { return $this->belongsTo(Terminal::class); }

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }

    /** @return HasMany<Shift, $this> */
    public function shifts(): HasMany { return $this->hasMany(Shift::class); }

    /**
     * The open trading day for this (store, terminal) today, if any.
     * `terminal_id` may be null — stores with no terminal binding still
     * get one day wrapper per date, just not till-scoped.
     */
    public static function openFor(int $storeId, ?int $terminalId): ?self
    {
        return self::query()
            ->where('store_id', $storeId)
            ->where('terminal_id', $terminalId)
            ->where('business_date', now()->toDateString())
            ->where('status', self::STATUS_OPEN)
            ->first();
    }
}
