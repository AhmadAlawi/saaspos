<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock Take (cycle count) document. Three states:
 *
 *   - `draft`     → operator is walking the shelves, filling in counts.
 *                   Editable. Nothing in the ledger yet.
 *   - `posted`    → variance lines have been written to `stock_movements`
 *                   via RecordStockMovement(type='count'). View-only.
 *                   Corrections happen via a new offsetting adjustment.
 *   - `cancelled` → operator abandoned the count without posting.
 *                   Items kept for audit; no movements were written.
 *
 * Number format: `COUNT-{STORE}-{YYYYMM}-{NNNN}`. Per-store/per-month
 * sequence, mirroring the purchase / sale numbering family. Generated
 * by {@see \App\Actions\Inventory\GenerateStockTakeNumber}.
 */
class StockTake extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_POSTED    = 'posted';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'store_id', 'number', 'name', 'take_date', 'notes',
        'status', 'posted_at', 'posted_by',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'take_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function isDraft(): bool     { return $this->status === self::STATUS_DRAFT; }
    public function isPosted(): bool    { return $this->status === self::STATUS_POSTED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }

    /** @param Builder<StockTake> $q */
    public function scopeDraft(Builder $q): void  { $q->where('status', self::STATUS_DRAFT); }

    /** @param Builder<StockTake> $q */
    public function scopePosted(Builder $q): void { $q->where('status', self::STATUS_POSTED); }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return HasMany<StockTakeItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockTakeItem::class);
    }
}
