<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A balanced double-entry transaction: a header plus ≥2 {@see JournalLine}s
 * whose debits equal their credits. Auto-generated from business operations
 * (source = sale / purchase / expense / …) or entered manually. Posted
 * entries are immutable — corrected via a reversing entry, never edited.
 *
 * The one canonical writer is {@see \App\Actions\Accounting\PostJournalEntry};
 * nothing else inserts into this table directly.
 */
class JournalEntry extends Model
{
    public const SOURCE_SALE             = 'sale';
    public const SOURCE_SALE_RETURN      = 'sale_return';
    public const SOURCE_SALE_VOID        = 'sale_void';
    public const SOURCE_PURCHASE         = 'purchase';
    public const SOURCE_PURCHASE_RETURN  = 'purchase_return';
    public const SOURCE_CUSTOMER_PAYMENT = 'customer_payment';
    public const SOURCE_SUPPLIER_PAYMENT = 'supplier_payment';
    public const SOURCE_EXPENSE          = 'expense';
    public const SOURCE_STOCK_ADJUSTMENT = 'stock_adjustment';
    public const SOURCE_SHIFT_VARIANCE   = 'shift_variance';
    public const SOURCE_OPENING_BALANCE  = 'opening_balance';
    public const SOURCE_MANUAL           = 'manual';
    public const SOURCE_CLOSING_ENTRY    = 'closing_entry';

    /** All sources, for the day-book filter. */
    public const SOURCES = [
        self::SOURCE_SALE, self::SOURCE_SALE_RETURN, self::SOURCE_SALE_VOID,
        self::SOURCE_PURCHASE, self::SOURCE_PURCHASE_RETURN,
        self::SOURCE_CUSTOMER_PAYMENT, self::SOURCE_SUPPLIER_PAYMENT,
        self::SOURCE_EXPENSE, self::SOURCE_STOCK_ADJUSTMENT, self::SOURCE_SHIFT_VARIANCE,
        self::SOURCE_OPENING_BALANCE, self::SOURCE_MANUAL, self::SOURCE_CLOSING_ENTRY,
    ];

    protected $fillable = [
        'store_id', 'number', 'entry_date', 'fiscal_period_id', 'source',
        'reference_type', 'reference_id', 'reversal_of_id', 'reversed_by_id',
        'description', 'is_posted', 'posted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'is_posted'  => 'boolean',
            'posted_at'  => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('sort_order');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function scopePosted(Builder $query): void
    {
        $query->where('is_posted', true);
    }

    public function scopeForStore(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopeSource(Builder $query, string $source): void
    {
        $query->where('source', $source);
    }
}
