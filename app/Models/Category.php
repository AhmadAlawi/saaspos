<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hierarchical product category (self-referencing via `parent_id`).
 * Soft-deletable; matches the schema in docs/database-schema.md §7.1.
 */
class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'color',
        'tax_group_id',
        'image_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** The system-default fallback category — non-deletable; the
     *  destination when a user deletes another category with products
     *  linked but doesn't pick a replacement. */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /** @return BelongsTo<TaxGroup, $this> */
    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /* ── Tree helpers ────────────────────────────────────────────── */

    /**
     * Walk this row's parent chain. Used by validators (cycle checks)
     * and presenters (breadcrumbs).
     *
     * @return array<int, int>  Ancestor ids, root → self's immediate parent.
     */
    public function ancestorIds(): array
    {
        $ids    = [];
        $cursor = $this->parent_id;
        while ($cursor && !\in_array($cursor, $ids, true)) {
            $ids[]  = $cursor;
            $cursor = static::query()->whereKey($cursor)->value('parent_id');
        }
        return array_reverse($ids);
    }

    /**
     * IDs of every descendant of this row, breadth-first.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids    = [];
        $queue  = [$this->id];
        while ($queue) {
            $children = static::query()
                ->whereIn('parent_id', $queue)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->all();
            $queue = array_values(array_diff($children, $ids));
            foreach ($queue as $childId) {
                $ids[] = $childId;
            }
        }
        return $ids;
    }

    /** True when `$candidate` is this row, or any of its descendants. */
    public function wouldCycleIfParentedTo(int|self $candidate): bool
    {
        $candidateId = $candidate instanceof self ? $candidate->id : $candidate;
        if ($candidateId === $this->id) {
            return true;
        }
        return \in_array($candidateId, $this->descendantIds(), true);
    }

    /* ── Scopes ──────────────────────────────────────────────────── */

    /** @param Builder<Category> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** @param Builder<Category> $q */
    public function scopeRoots(Builder $q): void
    {
        $q->whereNull('parent_id');
    }

    /** @param Builder<Category> $q */
    public function scopeOrdered(Builder $q): void
    {
        // DESC sort: newest manual-ordered category first. New rows are
        // created with `max(sort_order) + 1` so a freshly-added category
        // surfaces at the top — system-wide convention (see memory
        // table-and-form-conventions). Drag-to-reorder still works:
        // the reorder endpoint writes new sort_order values relative
        // to the dropped position, just in DESC visual semantics.
        $q->orderByDesc('sort_order')->orderByDesc('id');
    }
}
