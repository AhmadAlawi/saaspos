<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabelLayout;
use App\Models\Product;
use App\Services\Barcodes\BarcodeRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Product label printer (docs/features/hardware.md §6.3). A small wizard
 * picks products + quantities + a paper/label size, and renders a print-ready
 * page of labels (name / SKU / price / barcode) the browser sends to a printer.
 *
 * Two layout families, both driven from `config/labels.php`:
 *   - `sheet` — an Avery-style grid on A4, for a laser / inkjet printer.
 *   - `roll`  — one label per page sized to the stock, for the thermal label
 *               printers most shops actually run. Same renderer; the layout's
 *               `type` just changes the `@page` size and the grid to 1×1.
 *
 * Raster ESC/POS label output via the print bridge remains a separate path;
 * this covers the universally-available browser-print route for both papers.
 */
class LabelController extends Controller
{
    /** Hard cap so a runaway quantity can't render a huge document. */
    private const MAX_LABELS = 2000;

    public function form(): View
    {
        $this->authorize('viewAny', Product::class);

        return view('admin.products.labels.index', [
            'layouts'      => config('labels.layouts'),
            'defaultLayout' => config('labels.default'),
        ]);
    }

    public function sheet(Request $request, BarcodeRenderer $barcodes): View
    {
        $this->authorize('viewAny', Product::class);

        $layouts = config('labels.layouts');

        $data = $request->validate([
            'product_id'   => ['required', 'array', 'min:1'],
            'product_id.*' => ['integer'],
            'quantity'     => ['array'],
            'quantity.*'   => ['nullable', 'integer', 'min:1', 'max:500'],
            'layout'       => ['required', 'string', 'in:'.implode(',', array_keys($layouts))],
            'fields'       => ['array'],
            'fields.*'     => ['in:name,sku,price,barcode'],
        ]);

        $fields = [
            'name'    => in_array('name', $data['fields'] ?? [], true),
            'sku'     => in_array('sku', $data['fields'] ?? [], true),
            'price'   => in_array('price', $data['fields'] ?? [], true),
            'barcode' => in_array('barcode', $data['fields'] ?? [], true),
        ];

        $products = Product::query()
            ->whereIn('id', $data['product_id'])
            ->get(['id', 'name', 'sku', 'barcode', 'selling_price'])
            ->keyBy('id');

        // Flatten to one cell per label, capped. Barcodes are rendered once
        // per product and reused across that product's copies.
        $svgCache = [];
        $cells = [];
        foreach ($data['product_id'] as $i => $id) {
            $product = $products->get((int) $id);
            if (! $product) {
                continue;
            }
            $qty = (int) ($data['quantity'][$i] ?? 1);
            $qty = max(1, $qty);

            if ($fields['barcode'] && ! array_key_exists($id, $svgCache)) {
                $code = (string) ($product->barcode ?: $product->sku);
                $svgCache[$id] = $code !== '' ? $barcodes->svg($code, 1.5, 26) : '';
            }

            $cell = [
                'name'        => $product->name,
                'sku'         => $product->sku,
                'barcode'     => (string) ($product->barcode ?: $product->sku),
                'price'       => format_money($product->selling_price),
                'barcode_svg' => $fields['barcode'] ? ($svgCache[$id] ?? '') : '',
            ];

            for ($n = 0; $n < $qty && count($cells) < self::MAX_LABELS; $n++) {
                $cells[] = $cell;
            }
            if (count($cells) >= self::MAX_LABELS) {
                break;
            }
        }

        $layout  = $layouts[$data['layout']];
        $perPage = (int) $layout['cols'] * (int) $layout['rows'];

        // Only present once an admin has opened the Label Designer for this
        // layout key (see LabelLayout::firstOrCreateForKey()) — otherwise
        // `sheet.blade.php` keeps rendering its original fixed-stack markup.
        $labelLayout = LabelLayout::with('elements')->where('layout_key', $data['layout'])->first();

        return view('admin.products.labels.sheet', [
            'cells'       => $cells,
            'pages'       => array_chunk($cells, max(1, $perPage)),
            'layout'      => $layout,
            'fields'      => $fields,
            'perPage'     => $perPage,
            'auto'        => $request->boolean('print', true),
            'labelLayout' => $labelLayout,
            'elements'    => $labelLayout ? $labelLayout->elements->keyBy('type') : collect(),
        ]);
    }
}
