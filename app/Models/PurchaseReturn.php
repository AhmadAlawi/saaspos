<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT  = 'draft';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'store_id', 'supplier_id', 'purchase_id',
        'number', 'return_date',
        'notes', 'reason_code_id',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date'        => 'date',
            'subtotal'           => 'decimal:4',
            'tax_amount'         => 'decimal:4',
            'additional_charges' => 'decimal:4',
            'grand_total'        => 'decimal:4',
            'refund_amount'      => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<PurchaseReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
