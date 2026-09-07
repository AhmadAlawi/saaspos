<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ReceiptTemplate;
use App\Models\ReceiptTemplateElement;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The "Canvas" designer — free x/y positioned elements, the counterpart
 * to {@see ReceiptTemplateBlockController} for `layout_mode === 'canvas'`
 * templates. Every mutation here is AJAX/JSON (drag-end, resize-end, and
 * property-panel edits all fire immediately) rather than the block
 * editor's full-page form posts — the designer page itself never reloads
 * while editing.
 */
class ReceiptTemplateElementController extends Controller
{
    private const PALETTE_TYPES = [
        'text', 'image', 'logo', 'items_table', 'totals', 'payments', 'barcode', 'qr',
        'field.date', 'field.time', 'field.sale_number', 'field.customer_name', 'field.cashier_name',
        'field.store_name', 'field.store_address', 'field.store_phone', 'field.grand_total',
        'field.tax_total', 'field.item_count', 'field.return_policy',
        'field.receipt_header', 'field.receipt_footer',
    ];

    public function edit(ReceiptTemplate $receiptTemplate): View
    {
        $this->authorize('settings.manage_receipt_templates');

        return view('admin.receipt-templates.canvas', [
            'template' => $receiptTemplate,
            'elements' => $receiptTemplate->elements,
            'paletteTypes' => self::PALETTE_TYPES,
        ]);
    }

    /**
     * Paper size lives on the template's own metadata form too, but that
     * form doesn't know about element positions — switching a canvas
     * template from 80mm to 58mm there would silently push already-placed
     * elements (and item-table columns) past the new, narrower printable
     * edge, the exact class of bug the column-width guard above exists
     * to prevent. This dedicated endpoint checks for that before saving
     * and requires `force` to proceed anyway once the admin's seen the list.
     */
    public function updatePaperSize(Request $request, ReceiptTemplate $receiptTemplate): JsonResponse
    {
        $this->authorize('settings.manage_receipt_templates');

        $data = $request->validate([
            'paper_size' => ['required', Rule::in(['58mm', '80mm', 'a4'])],
            'force'      => ['sometimes', 'boolean'],
        ]);

        $printableMm = $this->printableWidthMm($data['paper_size']);
        $overflowing = [];
        foreach ($receiptTemplate->elements as $el) {
            if ($el->width !== null && ((float) $el->x + (float) $el->width) > $printableMm) {
                $overflowing[] = ucfirst(str_replace(['_', '.'], ' ', $el->type))." (#{$el->id})";
            }
            if ($el->type === 'items_table') {
                $colSum = array_sum(array_map(fn ($c) => (float) ($c['width_mm'] ?? 0), $el->config['columns'] ?? []));
                if ($colSum > $printableMm) {
                    $overflowing[] = "Item table columns (#{$el->id})";
                }
            }
        }

        if ($overflowing !== [] && ! $request->boolean('force')) {
            return response()->json([
                'message' => count($overflowing).' element(s) would print past the new paper\'s edge: '.implode(', ', $overflowing),
                'overflowing' => $overflowing,
            ], 409);
        }

        $receiptTemplate->update(['paper_size' => $data['paper_size']]);

        return response()->json(['ok' => true, 'paper_size' => $receiptTemplate->paper_size]);
    }

    public function store(Request $request, ReceiptTemplate $receiptTemplate): JsonResponse
    {
        $this->authorize('settings.manage_receipt_templates');

        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', self::PALETTE_TYPES)],
            'x'    => ['sometimes', 'numeric', 'min:0'],
            'y'    => ['sometimes', 'numeric', 'min:0'],
        ]);

        $nextZ = (int) $receiptTemplate->elements()->max('z_index') + 1;
        $element = ReceiptTemplateElement::create([
            'receipt_template_id' => $receiptTemplate->id,
            'type'                => $data['type'],
            'x'                   => $data['x'] ?? 5,
            'y'                   => $data['y'] ?? 5,
            'z_index'             => $nextZ,
            'font_size'           => 10,
            'font_family'         => 'dejavu_sans',
            'align'               => 'left',
            'is_bold'             => false,
            'is_visible'          => true,
        ]);

        return response()->json($this->serialize($element), 201);
    }

    /**
     * Everything about an element is editable through this one endpoint —
     * position/size from drag/resize, font/align/bold/visibility and
     * type-specific `config` (text, image path, item-table columns) from
     * the property panel. All fields are `sometimes` since the designer
     * only ever sends what actually changed.
     */
    public function update(Request $request, ReceiptTemplate $receiptTemplate, ReceiptTemplateElement $element): JsonResponse
    {
        $this->authorize('settings.manage_receipt_templates');
        $this->ensureBelongsToTemplate($receiptTemplate, $element);

        $data = $request->validate([
            'x'          => ['sometimes', 'numeric'],
            'y'          => ['sometimes', 'numeric'],
            'width'      => ['sometimes', 'nullable', 'numeric', 'min:1'],
            'height'     => ['sometimes', 'nullable', 'numeric', 'min:1'],
            'z_index'    => ['sometimes', 'integer'],
            'font_size'  => ['sometimes', 'integer', 'min:6', 'max:72'],
            'font_family' => ['sometimes', 'in:'.implode(',', ReceiptTemplateElement::FONTS)],
            'align'      => ['sometimes', 'in:left,center,right'],
            'is_bold'    => ['sometimes', 'boolean'],
            'is_visible' => ['sometimes', 'boolean'],
            'text'       => ['sometimes', 'nullable', 'string', 'max:2000'],
            'columns'    => ['sometimes', 'array'],
            'columns.*.field'    => ['required_with:columns', 'string'],
            'columns.*.label'    => ['sometimes', 'string'],
            'columns.*.width_mm' => ['required_with:columns', 'numeric', 'min:1'],
            'show_discount_subline' => ['sometimes', 'boolean'],
            'show_batch_subline'    => ['sometimes', 'boolean'],
            'show_sku_subline'      => ['sometimes', 'boolean'],
            'show_barcode_subline'  => ['sometimes', 'boolean'],
            'show_borders'          => ['sometimes', 'boolean'],
            'show_row_dividers'     => ['sometimes', 'boolean'],
            'show_currency'         => ['sometimes', 'boolean'],
            'subline_gap_mm'        => ['sometimes', 'numeric', 'min:0.5', 'max:20'],
            'image'        => ['sometimes', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'image_remove' => ['sometimes', 'boolean'],
        ]);

        $update = array_intersect_key($data, array_flip(['x', 'y', 'width', 'height', 'z_index', 'font_size', 'font_family', 'align', 'is_bold', 'is_visible']));

        if ($element->type === 'text' && $request->has('text')) {
            $update['config'] = ['text' => trim((string) $data['text'])];
        }

        if ($element->type === 'items_table' && ($request->has('columns') || $request->has('show_discount_subline') || $request->has('show_batch_subline') || $request->has('show_sku_subline') || $request->has('show_barcode_subline') || $request->has('show_borders') || $request->has('show_row_dividers') || $request->has('show_currency') || $request->has('subline_gap_mm'))) {
            $config = $element->config ?? [];
            if ($request->has('columns')) {
                $totalWidthMm = array_sum(array_map(fn ($c) => (float) ($c['width_mm'] ?? 0), $data['columns']));
                $printableMm = $this->printableWidthMm($receiptTemplate->paper_size);
                if ($totalWidthMm > $printableMm) {
                    return response()->json([
                        'message' => "Columns total {$totalWidthMm}mm, but {$receiptTemplate->paper_size} paper only prints about {$printableMm}mm wide — narrow one or more columns.",
                        'errors' => ['columns' => ["Columns total {$totalWidthMm}mm, but {$receiptTemplate->paper_size} paper only prints about {$printableMm}mm wide."]],
                    ], 422);
                }
                $config['columns'] = $data['columns'];
            }
            if ($request->has('show_discount_subline')) {
                $config['show_discount_subline'] = $request->boolean('show_discount_subline');
            }
            if ($request->has('show_batch_subline')) {
                $config['show_batch_subline'] = $request->boolean('show_batch_subline');
            }
            if ($request->has('show_sku_subline')) {
                $config['show_sku_subline'] = $request->boolean('show_sku_subline');
            }
            if ($request->has('show_barcode_subline')) {
                $config['show_barcode_subline'] = $request->boolean('show_barcode_subline');
            }
            if ($request->has('show_borders')) {
                $config['show_borders'] = $request->boolean('show_borders');
            }
            if ($request->has('show_row_dividers')) {
                $config['show_row_dividers'] = $request->boolean('show_row_dividers');
            }
            if ($request->has('show_currency')) {
                $config['show_currency'] = $request->boolean('show_currency');
            }
            if ($request->has('subline_gap_mm')) {
                $config['subline_gap_mm'] = (float) $data['subline_gap_mm'];
            }
            $update['config'] = $config;
        }

        if (in_array($element->type, ['image', 'logo'], true)) {
            $config = $element->config ?? [];
            if ($request->hasFile('image')) {
                $this->deleteStoredImage($element);
                $config['image_path'] = $request->file('image')->store('receipt-templates', 'public');
            } elseif ($request->boolean('image_remove')) {
                $this->deleteStoredImage($element);
                $config['image_path'] = null;
            }
            if (array_key_exists('image_path', $config)) {
                $update['config'] = $config;
            }
        }

        $element->update($update);

        return response()->json($this->serialize($element->refresh()));
    }

    public function destroy(ReceiptTemplate $receiptTemplate, ReceiptTemplateElement $element): JsonResponse
    {
        $this->authorize('settings.manage_receipt_templates');
        $this->ensureBelongsToTemplate($receiptTemplate, $element);

        if (in_array($element->type, ['image', 'logo'], true)) {
            $this->deleteStoredImage($element);
        }
        $element->delete();

        return response()->json(['ok' => true]);
    }

    /** Same real-render approach as {@see ReceiptTemplateBlockController::preview()} — the actual `sales.receipt` view, embedded in an <iframe>. */
    public function preview(ReceiptTemplate $receiptTemplate): Response
    {
        $this->authorize('settings.manage_receipt_templates');

        $sale = Sale::with([
            'store', 'customer', 'cashier',
            'items.product', 'items.variant', 'items.batch',
            'payments.paymentMethod',
        ])->latest()->first();

        if (! $sale) {
            return response(
                '<p style="font-family:sans-serif;padding:24px;color:#6b7280;">'
                .__('receipt_templates.blocks.no_sales_yet').'</p>',
            );
        }

        $html = ViewFacade::make('sales.receipt', [
            'sale'       => $sale,
            'company'    => Company::current() ?? new Company(),
            'paper'      => $receiptTemplate->paper_size,
            'auto'       => false,
            'template'   => $receiptTemplate->loadMissing('elements'),
            'receiptUrl' => null,
        ])->render();

        return response($html);
    }

    private function ensureBelongsToTemplate(ReceiptTemplate $receiptTemplate, ReceiptTemplateElement $element): void
    {
        abort_unless($element->receipt_template_id === $receiptTemplate->id, 404);
    }

    /** Same 203dpi-assumed printable widths as the canvas editor view and {@see CanvasReceiptRasterizer::DOTS_PER_MM} — kept here too since column-width validation has to happen server-side, not just in the JS editor. */
    private function printableWidthMm(string $paperSize): float
    {
        return match ($paperSize) {
            '58mm' => 48.0,
            'a4'   => 200.0,
            default => 64.0,
        };
    }

    private function deleteStoredImage(ReceiptTemplateElement $element): void
    {
        $path = $element->config['image_path'] ?? null;
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /** @return array<string, mixed> */
    private function serialize(ReceiptTemplateElement $element): array
    {
        return [
            'id'         => $element->id,
            'type'       => $element->type,
            'x'          => (float) $element->x,
            'y'          => (float) $element->y,
            'width'      => $element->width !== null ? (float) $element->width : null,
            'height'     => $element->height !== null ? (float) $element->height : null,
            'z_index'    => $element->z_index,
            'font_size'  => $element->font_size,
            'font_family' => $element->font_family,
            'align'      => $element->align,
            'is_bold'    => $element->is_bold,
            'is_visible' => $element->is_visible,
            'config'     => $element->config,
        ];
    }
}
