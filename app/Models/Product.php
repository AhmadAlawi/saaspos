<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catalog product. Matches the schema in
 * database/migrations/2026_05_21_000007_create_catalog.php.
 *
 * Variants, batches, kits, per-store stock, and multi-image gallery
 * are deliberately NOT modelled on this class — each gets its own
 * model when the corresponding feature lands. The flagship POS use
 * cases (ringing up a "simple" product at a single price) work off
 * just these columns.
 */
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'slug',
        'category_id',
        'brand_id',
        'unit_id',
        'tax_group_id',
        'is_tax_inclusive',
        'description',
        'short_description',
        'type',
        'cost_price',
        'selling_price',
        'sale_price',
        'mrp',
        'markup_percent',
        'sold_by_weight',
        'scale_plu',
        'track_stock',
        'track_batches',
        'track_expiry',
        'expiry_date',
        'reorder_level',
        'reorder_quantity',
        'hsn_code',
        'pharmacy_schedule',
        'generic_name',
        'manufacturer',
        'is_active',
        'is_featured',
        'image_path',
        'image_ref',
        'meta',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cost_price'       => 'decimal:4',
            'selling_price'    => 'decimal:4',
            'sale_price'       => 'decimal:4',
            'mrp'              => 'decimal:4',
            'markup_percent'   => 'decimal:4',
            'reorder_level'    => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'expiry_date'      => 'date',
            'is_tax_inclusive' => 'boolean',
            'sold_by_weight'   => 'boolean',
            'scale_plu'        => 'integer',
            'track_stock'      => 'boolean',
            'track_batches'    => 'boolean',
            'track_expiry'     => 'boolean',
            'is_active'        => 'boolean',
            'is_featured'      => 'boolean',
            'meta'             => 'array',
        ];
    }

    /**
     * What actually gets charged: the active sale price when set, else
     * the regular selling price. No variant fallback needed here —
     * `ProductVariant::effectiveChargePrice` handles that side.
     *
     * @return Attribute<string, never>
     */
    protected function chargePrice(): Attribute
    {
        return Attribute::get(fn () => $this->sale_price !== null ? (string) $this->sale_price : (string) $this->selling_price);
    }

    /**
     * Variant attribute definitions for `type = 'variant'` products,
     * stored in `meta->variant_attributes`. Shape:
     *
     *   [ ['name' => 'Color', 'values' => ['Red', 'Blue']], ... ]
     *
     * These drive the editor's matrix generator — the cartesian product
     * of every attribute's values becomes the variant rows. Kept on the
     * product (rather than a shared attributes table) because v1 scopes
     * attributes per-product; promoting to a global library later is a
     * data migration, not a schema break.
     *
     * @return array<int, array{name: string, values: array<int, string>}>
     */
    public function variantAttributes(): array
    {
        $meta = $this->meta;
        return is_array($meta) && isset($meta['variant_attributes']) && is_array($meta['variant_attributes'])
            ? $meta['variant_attributes']
            : [];
    }

    /**
     * Suggested selling price derived from a given cost + this product's
     * `markup_percent`. Returns null when no markup is set (owner is
     * managing the selling price by hand). Decimal string at 4dp,
     * matching every other money path in the system.
     *
     * Formula: `cost × (1 + markup/100)`.
     */
    public function suggestedSellingFromCost(string|float|int $cost): ?string
    {
        $markup = $this->markup_percent !== null ? (string) $this->markup_percent : null;
        if ($markup === null || bccomp($markup, '0', 4) <= 0) {
            return null;
        }
        $costStr = (string) $cost;
        if ($costStr === '' || bccomp($costStr, '0', 4) <= 0) {
            return null;
        }
        $multiplier = bcadd('1', bcdiv($markup, '100', 8), 8);
        $raw        = bcmul($costStr, $multiplier, 8);
        // Round half-up to 4dp.
        $nudged = bcadd($raw, '0.00005', 8);
        return bcadd($nudged, '0', 4);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<TaxGroup, $this> */
    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class);
    }

    /**
     * Per-store price overrides. Any subset of (cost, selling, mrp)
     * may be set; nulls fall through to this product's defaults at
     * resolve time. See {@see App\Actions\Products\ResolveProductPrice}.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ProductStorePrice, $this>
     */
    public function prices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductStorePrice::class);
    }

    /**
     * Child SKUs for `type = 'variant'` products. The parent product
     * is non-sellable directly in this mode — every receipt line
     * references a specific variant via `sale_items.variant_id`.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ProductVariant, $this>
     */
    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Extra/alternate barcodes beyond the primary `barcode` column (a
     * case/carton code from a different supplier, a relabeled batch,
     * etc.). See {@see \App\Models\ProductBarcode}.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ProductBarcode, $this>
     */
    public function barcodes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    /**
     * Resolve a product by its primary barcode OR any of its extra ones.
     * The single place every "scan/type a code, find the product" call
     * site should go through, so a new fallback source only ever needs
     * adding here once.
     */
    public static function findByAnyBarcode(string $code): ?self
    {
        return static::query()
            ->where('barcode', $code)
            ->orWhereHas('barcodes', fn ($q) => $q->where('barcode', $code))
            ->first();
    }

    /** Is this a variant-parent product (vs. a simple / kit SKU)? */
    public function isVariantType(): bool
    {
        return $this->type === 'variant';
    }

    /** Is this a kit / bundle product? */
    public function isKitType(): bool
    {
        return $this->type === 'kit';
    }

    /**
     * Component lines for `type = 'kit'` products. Each row points at
     * another product (and optionally a specific variant) plus the
     * quantity that ships in the bundle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<ProductKitItem, $this>
     */
    public function kitItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductKitItem::class, 'parent_product_id')->orderBy('sort_order');
    }

    /**
     * Public URL of the product image, or null. Relative URL — see
     * {@see Brand::logoUrl()} for the reasoning (works regardless of
     * APP_URL or host:port).
     *
     * @return Attribute<?string, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path
            ? '/storage/'.ltrim($this->image_path, '/')
            : null);
    }

    /** @param Builder<Product> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /**
     * Default admin-list / export ordering: newest first. Matches how
     * Brand and Unit lists are sorted (the established convention for
     * flat catalog data).
     *
     * @param Builder<Product> $q
     */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderByDesc('id');
    }
}
