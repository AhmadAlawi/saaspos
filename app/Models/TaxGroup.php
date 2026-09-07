<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bundle of tax components applied to a sale line. Categories and
 * products reference a tax group via `tax_group_id`; at checkout the
 * resolver prefers the category's group, falling back to the product's
 * own. Schema lives in `database/migrations/2026_05_21_000005_create_taxes.php`.
 */
class TaxGroup extends Model
{
    protected $fillable = [
        'code',
        'name',
        'logical_code',
        'applies_when',
        'classification',
        'is_reverse_charge',
        'is_inclusive',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'applies_when'      => 'array',
            'is_reverse_charge' => 'boolean',
            'is_inclusive'      => 'boolean',
            'is_default'        => 'boolean',
            'is_active'         => 'boolean',
        ];
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** Products tagged with this group directly (vs via their category).
     *  Used by the delete guard — a group can be deleted only when no
     *  products reference it. Categories are nulled via FK behaviour. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Tax components participating in this group, with their sort order. */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(TaxComponent::class, 'tax_group_components')
            ->withPivot(['sort_order', 'valid_from', 'valid_to'])
            ->orderBy('tax_group_components.sort_order');
    }

    /* ── Scopes ──────────────────────────────────────────────────── */

    /** @param Builder<TaxGroup> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** Default first (when `is_default = true`), then alphabetical. */
    public function scopeOrdered(Builder $q): void
    {
        $q->orderByDesc('is_default')->orderBy('name');
    }

    /** The single row flagged `is_default = true`, or null if none. */
    public static function default(): ?self
    {
        return self::query()->where('is_default', true)->first();
    }
}
