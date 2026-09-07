<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A return / refund against a previously-completed sale.
 *
 * Lines live in {@see SaleReturnItem}, one per refunded sale line. The
 * money flows through one of three buckets (cash, store credit, or
 * original method) so the cash drawer reconciliation can pick out
 * cash refunds and the customer-credit ledger can pick out store
 * credit. The parent sale's status is bumped to
 * `partially_refunded` / `refunded` by the recording action, not by
 * this model.
 */
class SaleReturn extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOIDED    = 'voided';

    public const GATEWAY_REFUND_SUCCEEDED = 'succeeded';
    public const GATEWAY_REFUND_FAILED    = 'failed';

    protected $fillable = [
        'store_id', 'sale_id', 'is_blind', 'linked_exchange_sale_id', 'client_uuid',
        'approved_by', 'original_currency_code', 'exchange_rate_to_active',
        'number', 'return_date', 'cashier_id', 'shift_id', 'reason_code_id',
        'subtotal', 'tax_total', 'grand_total',
        'refund_method_id', 'refunded_to_store_credit', 'refunded_in_cash',
        'refunded_to_original_method', 'restock', 'notes', 'status',
        'gateway_refund_status', 'gateway_refund_attempts', 'gateway_refund_error', 'gateway_refunded_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date'                 => 'date',
            'subtotal'                    => 'decimal:4',
            'tax_total'                   => 'decimal:4',
            'grand_total'                 => 'decimal:4',
            'refunded_to_store_credit'    => 'decimal:4',
            'refunded_in_cash'            => 'decimal:4',
            'refunded_to_original_method' => 'decimal:4',
            'exchange_rate_to_active'     => 'decimal:10',
            'restock'                     => 'boolean',
            'is_blind'                    => 'boolean',
            'gateway_refunded_at'         => 'datetime',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** @return BelongsTo<ReturnReason, $this> */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class, 'reason_code_id');
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function refundMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'refund_method_id');
    }

    /** @return HasMany<SaleReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
