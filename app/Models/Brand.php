<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Product brand. Flat list — no hierarchy, no children.
 */
class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /** The system-default fallback brand — non-deletable; the
     *  destination when a user deletes another brand with products
     *  linked but doesn't pick a replacement. */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /**
     * Public URL of the brand logo (or null if none).
     *
     * Returns a relative URL (e.g. `/storage/brands/abc.png`) instead of
     * piping through `Storage::disk('public')->url()`. The disk's `url`
     * config is `APP_URL.'/storage'`, which means anyone with a wrong
     * APP_URL (or hitting the app on a different host/port than what's
     * configured) sees broken images. A leading-slash relative URL
     * always resolves against whatever host the browser is currently
     * on — works for `php artisan serve`, Laragon, prod domains, the
     * lot. When we later wire S3 / Cloudfront support, this accessor
     * gets a disk-aware branch.
     *
     * @return Attribute<?string, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->logo_path
            ? '/storage/'.ltrim($this->logo_path, '/')
            : null);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @param Builder<Brand> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<Brand> $q */
    public function scopeOrdered(Builder $q): void
    {
        // Newest first across every admin list / export. The
        // sort_order column is kept on the row for future drag-to-
        // reorder; if/when that lands, switch the ordering at that
        // point.
        $q->orderByDesc('id');
    }
}
