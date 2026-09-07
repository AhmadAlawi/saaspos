<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A child SKU under a `type=variant` parent product.
 *
 * Each variant has its own SKU + (optional) barcode + cost + selling
 * price + active flag. `attributes` is a free-form JSON map; for v1
 * we store at least a `label` key ("Red / Small", "500mg · 10 tablets")
 * which is what the cashier sees on the receipt and in the picker.
 *
 * Price fields are nullable — null falls through to the parent
 * product's defaults at sale time.
 *
 * Naming note: the JSON column is named `attributes`, which clashes
 * with Eloquent's protected `$attributes` storage array. Inside model
 * methods we use `$this->getAttribute('attributes')` explicitly to
 * get the JSON-cast value (an array). External callers can still use
 * `$variant->attributes` because PHP's `__get` magic fires through
 * the cast.
 */
class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'attributes',
        'cost_price',
        'selling_price',
        'sale_price',
        'mrp',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes'    => 'array',
            'cost_price'    => 'decimal:4',
            'selling_price' => 'decimal:4',
            'sale_price'    => 'decimal:4',
            'mrp'           => 'decimal:4',
            'is_active'     => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Human-readable display label — `attributes.label` with a
     * fallback to the SKU so we always have something to show.
     *
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::get(function () {
            $attrs = $this->getAttribute('attributes');
            if (is_array($attrs)) {
                if (!empty($attrs['label'])) return (string) $attrs['label'];
                if ($attrs !== []) return implode(' · ', $attrs);
            }
            return (string) $this->sku;
        });
    }

    /**
     * Effective selling price — variant override or parent default.
     *
     * @return Attribute<?string, never>
     */
    protected function effectiveSellingPrice(): Attribute
    {
        return Attribute::get(fn () => $this->selling_price !== null
            ? (string) $this->selling_price
            : (string) ($this->product?->selling_price));
    }

    /**
     * Effective sale (promotional) price — variant override, else the
     * parent's, else null when neither has an active promo.
     *
     * @return Attribute<?string, never>
     */
    protected function effectiveSalePrice(): Attribute
    {
        return Attribute::get(function () {
            if ($this->sale_price !== null) return (string) $this->sale_price;
            return $this->product?->sale_price !== null ? (string) $this->product->sale_price : null;
        });
    }

    /**
     * What actually gets charged: the sale price when one's active,
     * else the regular effective selling price.
     *
     * @return Attribute<string, never>
     */
    protected function effectiveChargePrice(): Attribute
    {
        return Attribute::get(fn () => $this->effective_sale_price ?? $this->effective_selling_price);
    }

    /** @return Attribute<?string, never> */
    protected function effectiveCostPrice(): Attribute
    {
        return Attribute::get(fn () => $this->cost_price !== null
            ? (string) $this->cost_price
            : (string) ($this->product?->cost_price));
    }
}
