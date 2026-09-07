<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptTemplateBlock extends Model
{
    // Seeded once per template, reorderable/hideable but not addable or
    // deletable — these render the actual sale data, not editable content.
    public const STRUCTURAL_TYPES = [
        'logo', 'store_info', 'items_table', 'totals', 'payments',
        'barcode', 'qr', 'hsn_summary',
    ];

    // Freely addable/removable/reorderable by the template editor.
    public const FREEFORM_TYPES = ['text', 'image', 'divider', 'spacer'];

    protected $fillable = [
        'receipt_template_id',
        'type',
        'sort_order',
        'is_visible',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'config'     => 'array',
        ];
    }

    public function isStructural(): bool
    {
        return in_array($this->type, self::STRUCTURAL_TYPES, true);
    }

    /** @return BelongsTo<ReceiptTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ReceiptTemplate::class, 'receipt_template_id');
    }
}
