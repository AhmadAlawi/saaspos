<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A scheduled, time-boxed discount — "20% off all Shoes, Aug 10-12" — as
 * opposed to the manual per-sale/per-line discount a cashier applies at
 * checkout. See {@see \App\Actions\Products\ResolveProductPrice} for how
 * this actually gets applied to a product's charge price.
 */
class PriceRule extends Model
{
    public const SCOPE_ALL      = 'all';
    public const SCOPE_CATEGORY = 'category';
    public const SCOPE_PRODUCT  = 'product';

    public const TYPE_PERCENT = 'pct';
    public const TYPE_AMOUNT  = 'amt';

    protected $fillable = [
        'name', 'scope', 'category_id', 'product_id', 'store_id',
        'discount_type', 'discount_value', 'starts_at', 'ends_at',
        'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'      => 'datetime',
            'ends_at'        => 'datetime',
            'is_active'      => 'boolean',
            'discount_value' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isRunning(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->is_active && $this->starts_at <= $at && $this->ends_at >= $at;
    }

    /** Currently-running rules — every column still needs the scope/store filter to be meaningful. */
    public function scopeRunning(Builder $q, ?Carbon $at = null): void
    {
        $at ??= now();
        $q->where('is_active', true)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at);
    }
}
