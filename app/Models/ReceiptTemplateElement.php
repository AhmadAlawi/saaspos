<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One freely-positioned field on a "Canvas"-mode {@see ReceiptTemplate}
 * (`layout_mode === 'canvas'`) — the counterpart to
 * {@see ReceiptTemplateBlock} for the block-based mode. Position/size are
 * millimeters, top-left origin; see {@see \App\Services\Hardware\CanvasReceiptRasterizer}
 * for the mm→dots conversion used on the ESC/POS side.
 */
class ReceiptTemplateElement extends Model
{
    // Only these carry a meaningful items_table config.columns list; all
    // other types use `config.text`/`config.image_path` or nothing.
    public const ITEM_TABLE_COLUMNS = ['name', 'sku', 'hsn', 'qty', 'unit_price', 'line_total'];

    // Keys stored in `font_family`. Only 'dejavu_sans' renders Arabic
    // correctly — CanvasReceiptRasterizer forces it whenever the element's
    // text contains Arabic, regardless of this value.
    public const FONTS = ['dejavu_sans', 'dejavu_serif', 'dejavu_mono'];

    protected $fillable = [
        'receipt_template_id',
        'type',
        'x',
        'y',
        'width',
        'height',
        'z_index',
        'font_size',
        'font_family',
        'align',
        'is_bold',
        'is_visible',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'x'          => 'decimal:2',
            'y'          => 'decimal:2',
            'width'      => 'decimal:2',
            'height'     => 'decimal:2',
            'z_index'    => 'integer',
            'font_size'  => 'integer',
            'is_bold'    => 'boolean',
            'is_visible' => 'boolean',
            'config'     => 'array',
        ];
    }

    /** @return BelongsTo<ReceiptTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReceiptTemplate::class, 'receipt_template_id');
    }

    public function isFieldPlaceholder(): bool
    {
        return str_starts_with($this->type, 'field.');
    }
}
