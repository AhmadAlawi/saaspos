<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'purchase_return_id', 'purchase_item_id',
        'quantity', 'unit_cost_snapshot',
        'tax_amount', 'line_total',
        'restock', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity'           => 'decimal:4',
            'unit_cost_snapshot' => 'decimal:4',
            'tax_amount'         => 'decimal:4',
            'line_total'         => 'decimal:4',
            'restock'            => 'boolean',
        ];
    }

    /** @return BelongsTo<PurchaseReturn, $this> */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /** @return BelongsTo<PurchaseItem, $this> */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
