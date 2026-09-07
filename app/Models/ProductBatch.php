<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A specific batch of a batch-tracked product — captured at receipt
 * time. Carries the batch number, manufacture / expiry dates, and a
 * running on-hand quantity that lives alongside (not inside)
 * `product_stock_levels`.
 *
 * The store-level total in `product_stock_levels` is the sum across
 * all batches for that (store, product, variant) tuple. The batch row
 * is the granularity needed for FEFO/FIFO picking, expiry alerts, and
 * pharmacy traceability.
 *
 * `initial_quantity` is captured at create time and stays immutable —
 * useful for batch-life reporting (how much of batch X has been sold).
 *
 * Soft-deleted = ARCHIVED. Every FK pointing here is `nullOnDelete`, so a hard
 * delete would blank `batch_id` on posted sales, purchases and ledger rows;
 * keeping the row means all that history still resolves while the batch drops
 * out of the pickers and the list. Only an EMPTY batch may be archived — see
 * {@see \App\Actions\Inventory\DeleteProductBatch}.
 */
class ProductBatch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id', 'product_id', 'variant_id',
        'batch_number', 'manufacture_date', 'expiry_date',
        'cost_price', 'selling_price', 'mrp',
        'quantity', 'initial_quantity',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_date' => 'date',
            'expiry_date'      => 'date',
            'cost_price'       => 'decimal:4',
            'selling_price'    => 'decimal:4',
            'mrp'              => 'decimal:4',
            'quantity'         => 'decimal:4',
            'initial_quantity' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /* ── Expiry helpers ──────────────────────────────────────── */

    /** Has the batch's expiry date already passed? Null expiry → false. */
    public function isExpired(?CarbonInterface $asOf = null): bool
    {
        if ($this->expiry_date === null) return false;
        $today = ($asOf ?? now())->startOfDay();
        return $this->expiry_date->startOfDay()->lt($today);
    }

    /** Will the batch expire within the next `$days` (inclusive)? */
    public function isExpiringSoon(int $days = 30, ?CarbonInterface $asOf = null): bool
    {
        if ($this->expiry_date === null) return false;
        $today = ($asOf ?? now())->startOfDay();
        $cutoff = $today->copy()->addDays($days);
        $exp = $this->expiry_date->startOfDay();
        return $exp->gte($today) && $exp->lte($cutoff);
    }

    /**
     * Live = still has stock AND hasn't expired — i.e. sellable today.
     *
     * The expiry half matters: without it a batch sitting on 200 expired units
     * counted as "Live", so the Live tab listed rows whose own status badge
     * read "Expired". A null expiry never expires, so it stays live.
     *
     * Expiring-soon batches ARE live (they're still sellable); that tab is an
     * overlapping warning, not a separate bucket.
     *
     * This is a REPORTING scope only — it doesn't gate selling. Whether an
     * expired batch can be sold is decided by the `block_expired_batch_sale`
     * company setting plus the `inventory.sell_expired` permission, and FEFO
     * picking does its own quantity check (see ResolveFefoBatch).
     *
     * @param Builder<ProductBatch> $q
     */
    public function scopeLive(Builder $q): void
    {
        $q->where('quantity', '>', 0)
          ->where(function ($w) {
              $w->whereNull('expiry_date')
                ->orWhereDate('expiry_date', '>=', now()->toDateString());
          });
    }

    /** Expired = expiry_date strictly before today (null expiry never expires). */
    /** @param Builder<ProductBatch> $q */
    public function scopeExpired(Builder $q): void
    {
        $q->whereNotNull('expiry_date')
          ->whereDate('expiry_date', '<', now()->toDateString());
    }

    /** Expires within `$days` days (today + N) — null expiry excluded. */
    /** @param Builder<ProductBatch> $q */
    public function scopeExpiringWithin(Builder $q, int $days = 30): void
    {
        $q->whereNotNull('expiry_date')
          ->whereDate('expiry_date', '>=', now()->toDateString())
          ->whereDate('expiry_date', '<=', now()->copy()->addDays($days)->toDateString());
    }
}
