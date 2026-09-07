<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on a Sale. Snapshots the product's identifying fields verbatim
 * (`product_name_snapshot`, `sku_snapshot`, `barcode_snapshot`,
 * `unit_cost_snapshot`) so returns and reports can show what the customer
 * saw on the receipt even if the product is later renamed, retired, or
 * its cost shifts.
 *
 * `tax_breakdown` carries the full {@see App\Services\Tax\TaxBreakdown}
 * JSON shape so reports + returns never recompute tax. Once written, it's
 * historical truth.
 */
class SaleItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id', 'product_id', 'variant_id', 'batch_id',
        'product_name_snapshot', 'sku_snapshot', 'barcode_snapshot', 'hsn_snapshot',
        'quantity', 'unit',
        'unit_price', 'unit_cost_snapshot',
        'discount_percent', 'discount_amount',
        'tax_group_id', 'tax_breakdown', 'tax_amount',
        'line_subtotal', 'line_total',
        'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tax_breakdown'      => 'array',
            'quantity'           => 'decimal:4',
            'unit_price'         => 'decimal:4',
            'unit_cost_snapshot' => 'decimal:4',
            'discount_percent'   => 'decimal:4',
            'discount_amount'    => 'decimal:4',
            'tax_amount'         => 'decimal:4',
            'line_subtotal'      => 'decimal:4',
            'line_total'         => 'decimal:4',
            'quantity_returned'  => 'decimal:4',
            'sort_order'         => 'integer',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'variant_id'); }

    /** @return BelongsTo<ProductBatch, $this> */
    public function batch(): BelongsTo { return $this->belongsTo(ProductBatch::class, 'batch_id'); }

    /** @return BelongsTo<TaxGroup, $this> */
    public function taxGroup(): BelongsTo { return $this->belongsTo(TaxGroup::class); }
}
