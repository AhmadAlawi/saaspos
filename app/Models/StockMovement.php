<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only ledger of every stock change. Inserted by
 * {@see \App\Actions\Inventory\RecordStockMovement}; never updated or
 * deleted (mistakes are corrected by a counter-movement).
 *
 * `type` is one of: opening, adjustment, sale, return, purchase,
 * transfer_out, transfer_in. `reference_type` + `reference_id` point
 * back at whatever document caused the change (StockAdjustment, Sale,
 * Purchase, StockTransfer).
 */
class StockMovement extends Model
{
    public $timestamps = false; // table has created_at only (useCurrent)

    protected $fillable = [
        'store_id', 'product_id', 'variant_id', 'batch_id',
        'quantity_delta', 'quantity_after',
        'type', 'reference_type', 'reference_id',
        'unit_cost', 'notes', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:4',
            'quantity_after' => 'decimal:4',
            'unit_cost'      => 'decimal:4',
            'created_at'     => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
