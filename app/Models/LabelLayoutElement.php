<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of the five fixed fields on a {@see LabelLayout} (name, sku, price,
 * barcode, image) — position/size in percent of the label box, so the same
 * designer UI works across every label size in `config('labels.layouts')`.
 */
class LabelLayoutElement extends Model
{
    public const TYPES = ['name', 'sku', 'price', 'barcode', 'image'];

    // Font-bearing types only (barcode/image use `scale` instead).
    public const FONT_TYPES = ['name', 'sku', 'price'];

    public const FONTS = ['sans', 'serif', 'mono'];

    protected $fillable = [
        'label_layout_id',
        'type',
        'x_pct',
        'y_pct',
        'width_pct',
        'font_size',
        'font_family',
        'align',
        'scale',
        'is_visible',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'x_pct'      => 'decimal:2',
            'y_pct'      => 'decimal:2',
            'width_pct'  => 'decimal:2',
            'font_size'  => 'integer',
            'scale'      => 'decimal:2',
            'is_visible' => 'boolean',
            'config'     => 'array',
        ];
    }

    /** @return BelongsTo<LabelLayout, $this> */
    public function labelLayout(): BelongsTo
    {
        return $this->belongsTo(LabelLayout::class);
    }
}
