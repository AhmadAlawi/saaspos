<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Table (`name`, `paper_size`, `template`, `is_default`, `is_active`)
 * ships with the base product (`2026_05_21_000015_create_receipts.php`)
 * but was never given a model/UI until this feature. `template`
 * (longText) is the vendor's original single-blob design — unused here;
 * our block-based renderer reads `blocks()` instead, so it's always
 * written as `''`.
 */
class ReceiptTemplate extends Model
{
    // 'blocks' = list-ordered sections (existing editor). 'canvas' = free
    // x/y positioned elements (see ReceiptTemplateElement) — the whole
    // receipt gets rasterized as one bitmap for ESC/POS in this mode
    // since thermal text mode has no concept of pixel positions.
    public const LAYOUT_BLOCKS = 'blocks';

    public const LAYOUT_CANVAS = 'canvas';

    protected $fillable = [
        'name',
        'paper_size',
        'template',
        'layout_mode',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    public function isCanvas(): bool
    {
        return $this->layout_mode === self::LAYOUT_CANVAS;
    }

    /** @return HasMany<ReceiptTemplateBlock> */
    public function blocks(): HasMany
    {
        return $this->hasMany(ReceiptTemplateBlock::class)->orderBy('sort_order');
    }

    /**
     * Ordered, visible-only blocks for rendering. A plain filtered
     * Collection rather than a relation method — callers just want a
     * list to loop over, not something re-queryable/eager-loadable.
     *
     * @return Collection<int, ReceiptTemplateBlock>
     */
    public function visibleBlocks(): Collection
    {
        return $this->blocks->where('is_visible', true)->values();
    }

    /** @return HasMany<ReceiptTemplateElement> */
    public function elements(): HasMany
    {
        return $this->hasMany(ReceiptTemplateElement::class)->orderBy('z_index');
    }

    /** @return Collection<int, ReceiptTemplateElement> */
    public function visibleElements(): Collection
    {
        return $this->elements->where('is_visible', true)->values();
    }
}
