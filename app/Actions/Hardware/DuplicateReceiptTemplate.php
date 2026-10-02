<?php

namespace App\Actions\Hardware;

use App\Models\ReceiptTemplate;
use App\Models\ReceiptTemplateBlock;
use App\Models\ReceiptTemplateElement;
use Illuminate\Support\Facades\DB;

/**
 * Deep-copy a {@see ReceiptTemplate} — a real "test this without risking
 * the live one" clone, not just the metadata row. `layout_mode` is a
 * one-time choice on the original ({@see \App\Http\Controllers\Admin\ReceiptTemplateController::store()}'s
 * docblock), so the copy inherits it and only that side's child rows
 * (blocks OR elements) get copied. The copy is never `is_default` and
 * never inherits `is_active` unchecked — it starts inactive so it can't
 * silently become "the" receipt anywhere just by existing.
 */
class DuplicateReceiptTemplate
{
    public function __invoke(ReceiptTemplate $template): ReceiptTemplate
    {
        return DB::transaction(function () use ($template) {
            $copy = ReceiptTemplate::create([
                'name'        => $this->copyName($template->name),
                'paper_size'  => $template->paper_size,
                'template'    => '',
                'layout_mode' => $template->layout_mode,
                'is_default'  => false,
                'is_active'   => false,
            ]);

            if ($template->layout_mode === ReceiptTemplate::LAYOUT_BLOCKS) {
                foreach ($template->blocks()->orderBy('sort_order')->get() as $block) {
                    ReceiptTemplateBlock::create([
                        'receipt_template_id' => $copy->id,
                        'type'                => $block->type,
                        'sort_order'          => $block->sort_order,
                        'is_visible'          => $block->is_visible,
                        'config'              => $block->config,
                    ]);
                }
            } else {
                foreach ($template->elements as $element) {
                    ReceiptTemplateElement::create([
                        'receipt_template_id' => $copy->id,
                        'type'                => $element->type,
                        'x'                   => $element->x,
                        'y'                   => $element->y,
                        'width'               => $element->width,
                        'height'              => $element->height,
                        'z_index'             => $element->z_index,
                        'font_size'           => $element->font_size,
                        'font_family'         => $element->font_family,
                        'align'               => $element->align,
                        'is_bold'             => $element->is_bold,
                        'is_visible'          => $element->is_visible,
                        'config'              => $element->config,
                    ]);
                }
            }

            return $copy;
        });
    }

    /** "Original Design" -> "Original Design (Copy)", "... (Copy)" -> "... (Copy 2)", etc. */
    private function copyName(string $name): string
    {
        $base = preg_replace('/\s\(Copy(?:\s\d+)?\)$/', '', $name);

        $n = 1;
        do {
            $candidate = $n === 1 ? "{$base} (Copy)" : "{$base} (Copy {$n})";
            $exists = ReceiptTemplate::where('name', $candidate)->exists();
            $n++;
        } while ($exists);

        return $candidate;
    }
}
