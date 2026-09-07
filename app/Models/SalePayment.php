<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per payment-method tender on a Sale. A split-tender ($300 card +
 * $50 cash) writes two rows; a single cash payment writes one.
 *
 * Slice 1 only writes the cash + manual-reference shapes. The `gateway_*`
 * columns are pre-allocated for Slice 2 (Stripe / Razorpay / Flutterwave)
 * so the schema doesn't need a migration when those land.
 */
class SalePayment extends Model
{
    protected $fillable = [
        'sale_id', 'customer_id', 'payment_method_id',
        'amount', 'tendered_amount', 'change_returned',
        'reference',
        'currency_code', 'amount_in_method_currency', 'exchange_rate_to_sale_currency',
        'cheque_number', 'cheque_bank', 'cheque_date', 'cheque_status',
        'gateway_provider', 'gateway_payment_id', 'gateway_signature', 'gateway_status',
        'client_uuid', 'notes',
        'paid_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'                         => 'decimal:4',
            'tendered_amount'                => 'decimal:4',
            'change_returned'                => 'decimal:4',
            'amount_in_method_currency'      => 'decimal:4',
            'exchange_rate_to_sale_currency' => 'decimal:10',
            'cheque_date'                    => 'date',
            'cheque_realized_at'             => 'datetime',
            'cheque_bounce_fee'              => 'decimal:4',
            'paid_at'                        => 'datetime',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    /** @return HasMany<SalePaymentMethodChange, $this> */
    public function methodChanges(): HasMany { return $this->hasMany(SalePaymentMethodChange::class); }

    /** Settlement rows are the ones written by RecordCustomerPayments — they
     *  carry a customer_id (sale_id may be null for unallocated credit).
     *  Sale-time tenders have customer_id null. */
    public function scopeSettlement($q): void { $q->whereNotNull('customer_id'); }
}
