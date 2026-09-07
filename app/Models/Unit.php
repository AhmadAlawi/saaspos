<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Measurement unit. Two flavours coexist in the same table:
 *   - base units (e.g. kg, l, m, pc) — base_unit_id is null
 *   - derived units (e.g. g, mg, lb) — point at a base via base_unit_id
 *     and carry a `conversion_factor` expressed in BASE units per
 *     one of this unit (1 dozen = 12 pc → factor 12).
 */
class Unit extends Model
{
    use SoftDeletes;

    public const CATEGORIES = ['count', 'weight', 'volume', 'length', 'area', 'time'];

    protected $fillable = [
        'code',
        'name',
        'category',
        'base_unit_id',
        'conversion_factor',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
            'conversion_factor' => 'decimal:10',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }

    /** @return HasMany<Unit, $this> */
    public function derivedUnits(): HasMany
    {
        return $this->hasMany(self::class, 'base_unit_id');
    }

    public function isBase(): bool
    {
        return $this->base_unit_id === null;
    }

    /** @param Builder<Unit> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<Unit> $q */
    public function scopeBases(Builder $q): void
    {
        $q->whereNull('base_unit_id');
    }

    /** @param Builder<Unit> $q */
    public function scopeOrdered(Builder $q): void
    {
        // Newest first across every admin list / export. Users can
        // re-sort by Code / Category / etc. via the data-table column
        // sorter — see `dataTableMixin.sortBy()`.
        $q->orderByDesc('id');
    }
}
