<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a purchase. Captures the ordered quantity, unit cost, and
 * per-line tax. Batch + expiry are populated on receipt — left null in
 * the draft slice unless the user fills them at draft time (some
 * shops know the batch from the PO).
 *
 * `received_quantity`, `additional_charges_share`, and `landed_unit_cost`
 * stay null in this slice; they get populated by the receive flow.
 */
class PurchaseItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'purchase_id', 'product_id', 'variant_id',
        'batch_id', 'batch_number', 'manufacture_date', 'expiry_date',
        'quantity', 'received_quantity',
        'unit_cost',
        'additional_charges_share', 'landed_unit_cost',
        'discount_percent', 'discount_amount',
        'tax_group_id', 'tax_amount',
        'line_total',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_date'         => 'date',
            'expiry_date'              => 'date',
            'quantity'                 => 'decimal:4',
            'received_quantity'        => 'decimal:4',
            'unit_cost'                => 'decimal:4',
            'additional_charges_share' => 'decimal:4',
            'landed_unit_cost'         => 'decimal:4',
            'discount_percent'         => 'decimal:4',
            'discount_amount'          => 'decimal:4',
            'tax_amount'               => 'decimal:4',
            'line_total'               => 'decimal:4',
            'sort_order'               => 'integer',
        ];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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

    /** @return BelongsTo<TaxGroup, $this> */
    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class);
    }
}
