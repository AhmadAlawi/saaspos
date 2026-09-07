<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per {@see \App\Actions\Sales\ChangeSalePaymentMethod} call —
 * append-only audit trail, never updated or deleted by application code.
 */
class SalePaymentMethodChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id', 'sale_payment_id',
        'old_payment_method_id', 'new_payment_method_id',
        'reason', 'changed_by', 'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }

    /** @return BelongsTo<SalePayment, $this> */
    public function payment(): BelongsTo { return $this->belongsTo(SalePayment::class, 'sale_payment_id'); }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function oldPaymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class, 'old_payment_method_id'); }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function newPaymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class, 'new_payment_method_id'); }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
