<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single allocation of a supplier payment. When the user records a
 * $500 payment split across three POs, three `purchase_payments` rows
 * are inserted — same `client_uuid` so they're recognised as one user
 * action.
 *
 * `purchase_id` is nullable to support an unallocated supplier credit
 * (advance to supplier, payment with no PO attached). When the typed
 * payment amount is larger than the sum of per-PO allocations the
 * leftover lands as one extra row with `purchase_id = null` — the
 * supplier's `outstanding_balance` is decremented past zero and the
 * negative reads as "credit available" per feature doc §7.3.
 *
 * Voiding (Slice 4b): a soft-mark via `voided_at` / `voided_by` /
 * `void_reason`. {@see App\Actions\Purchases\VoidSupplierPayment}
 * reverses the cash effect (PO balance + supplier outstanding) inside
 * its own transaction and fires `supplier_payment.voided` so the
 * future accounting slice can post the reversal journal.
 */
class PurchasePayment extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'purchase_id', 'supplier_id', 'store_id',
        'payment_method_id',
        'payment_date', 'amount',
        'reference', 'client_uuid', 'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount'       => 'decimal:4',
            'voided_at'    => 'datetime',
        ];
    }

    /** True when this row has been voided (no cash effect any more). */
    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** True when the row carries unallocated supplier credit (no PO link). */
    public function isUnallocated(): bool
    {
        return $this->purchase_id === null;
    }

    /** @param Builder<PurchasePayment> $q */
    public function scopeActive(Builder $q): void
    {
        $q->whereNull('voided_at');
    }

    /** @param Builder<PurchasePayment> $q */
    public function scopeVoided(Builder $q): void
    {
        $q->whereNotNull('voided_at');
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /** @return BelongsTo<User, $this> */
    public function voider(): BelongsTo { return $this->belongsTo(User::class, 'voided_by'); }
}
