<?php

namespace App\Actions\Hardware;

use App\Models\ReceiptTemplate;
use App\Models\Terminal;

/**
 * Which {@see ReceiptTemplate} applies to a print, most-specific wins —
 * same precedence shape as {@see \App\Actions\Products\ResolveProductPrice}'s
 * price-rule resolution (product > category > all): terminal override >
 * store override > the one template flagged `is_default` > none.
 *
 * Returning `null` means "no template exists yet" — both
 * {@see \App\Services\Hardware\EscPosFormatter} and
 * `resources/views/sales/receipt.blade.php` fall back to their original
 * `Company`-field-driven rendering in that case, so every existing
 * install prints exactly as it always has until an admin creates and
 * assigns a template. This is what makes templates purely additive.
 */
class ResolveReceiptTemplate
{
    public function __invoke(?Terminal $terminal): ?ReceiptTemplate
    {
        if ($terminal?->receipt_template_id) {
            $template = $terminal->receiptTemplate()->with(['blocks', 'elements'])->where('is_active', true)->first();
            if ($template) {
                return $template;
            }
        }

        $storeTemplateId = $terminal?->store?->receipt_template_id;
        if ($storeTemplateId) {
            $template = ReceiptTemplate::with('blocks')->where('is_active', true)->find($storeTemplateId);
            if ($template) {
                return $template;
            }
        }

        return ReceiptTemplate::with('blocks')->where('is_default', true)->where('is_active', true)->first();
    }
}
