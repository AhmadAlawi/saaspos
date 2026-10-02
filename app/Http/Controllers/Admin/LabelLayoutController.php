<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabelLayout;
use App\Models\LabelLayoutElement;
use App\Models\Product;
use App\Services\Barcodes\BarcodeRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The web "Label Designer" — free x/y positioning for the product label
 * sheet (docs/features/hardware.md §6.3), mirroring the mobile app's own
 * per-label-spec Label Layout Designer. Same one-endpoint-updates-anything
 * shape as {@see ReceiptTemplateElementController}, but there's nothing to
 * add/remove here: every layout has exactly the same 5 fixed elements
 * (name, sku, price, barcode, image), seeded on first visit by
 * {@see LabelLayout::firstOrCreateForKey()}.
 */
class LabelLayoutController extends Controller
{
    public function edit(string $layoutKey): View
    {
        $this->authorize('viewAny', Product::class);

        $layouts = config('labels.layouts');
        abort_unless(array_key_exists($layoutKey, $layouts), 404);

        $labelLayout = LabelLayout::firstOrCreateForKey($layoutKey);

        return view('admin.products.labels.designer', [
            'layoutKey'   => $layoutKey,
            'layout'      => $labelLayout->effectiveLayout($layouts[$layoutKey]),
            'labelLayout' => $labelLayout,
            'elements'    => $labelLayout->elements->keyBy('type'),
            // Same sample content the preview iframe renders — the canvas
            // now server-renders the real `_cell-canvas` partial instead of
            // synthetic placeholder boxes, so what you drag IS what prints.
            'cell'        => $this->sampleCell(),
        ]);
    }

    public function update(Request $request, string $layoutKey, string $type): JsonResponse
    {
        $this->authorize('viewAny', Product::class);
        abort_unless(array_key_exists($layoutKey, config('labels.layouts')), 404);
        abort_unless(in_array($type, LabelLayoutElement::TYPES, true), 404);

        $labelLayout = LabelLayout::firstOrCreateForKey($layoutKey);
        $element = $labelLayout->elements->firstWhere('type', $type);
        abort_unless($element, 404);

        $data = $request->validate([
            'x_pct'       => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'y_pct'       => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'width_pct'   => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:100'],
            'height_pct'  => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:100'],
            'font_size'   => ['sometimes', 'integer', 'min:5', 'max:48'],
            'font_family' => ['sometimes', 'in:'.implode(',', LabelLayoutElement::FONTS)],
            'align'       => ['sometimes', 'in:left,center,right'],
            'scale'       => ['sometimes', 'numeric', 'min:0.1', 'max:3'],
            'is_visible'  => ['sometimes', 'boolean'],
            'image'        => ['sometimes', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'image_remove' => ['sometimes', 'boolean'],
        ]);

        $update = array_intersect_key($data, array_flip(['x_pct', 'y_pct', 'width_pct', 'height_pct', 'font_size', 'font_family', 'align', 'scale', 'is_visible']));

        if ($type === 'image') {
            $config = $element->config ?? [];
            if ($request->hasFile('image')) {
                $this->deleteStoredImage($element);
                $config['image_path'] = $request->file('image')->store('label-templates', 'public');
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

    public function preview(string $layoutKey): Response
    {
        $this->authorize('viewAny', Product::class);

        $layouts = config('labels.layouts');
        abort_unless(array_key_exists($layoutKey, $layouts), 404);

        $labelLayout = LabelLayout::firstOrCreateForKey($layoutKey);

        return response()->view('admin.products.labels.preview', [
            'layout'   => $labelLayout->effectiveLayout($layouts[$layoutKey]),
            'cell'     => $this->sampleCell(),
            'elements' => $labelLayout->elements->keyBy('type'),
        ]);
    }

    /**
     * Update the label's own physical size for this shop — overrides
     * `config('labels.layouts')[$layoutKey]`'s stock mm size. Separate
     * endpoint from element `update()` above since this patches the
     * {@see LabelLayout} row itself, not one of its elements.
     */
    public function updateDimensions(Request $request, string $layoutKey): JsonResponse
    {
        $this->authorize('viewAny', Product::class);
        abort_unless(array_key_exists($layoutKey, config('labels.layouts')), 404);

        $labelLayout = LabelLayout::firstOrCreateForKey($layoutKey);

        $data = $request->validate([
            'label_w_mm' => ['required', 'numeric', 'min:5', 'max:500'],
            'label_h_mm' => ['required', 'numeric', 'min:5', 'max:500'],
        ]);

        $labelLayout->update($data);

        $layout = $labelLayout->effectiveLayout(config('labels.layouts')[$layoutKey]);

        return response()->json([
            'label_w_mm' => (float) $layout['label_w_mm'],
            'label_h_mm' => (float) $layout['label_h_mm'],
        ]);
    }

    /**
     * The canvas's own drag/resize surface, re-rendered fresh after every
     * edit — same `_cell-canvas` partial the true-size preview and the
     * print sheet use (real fonts/barcode/image, not a placeholder), just
     * at the canvas's px-per-mm zoom. The JS re-fetches this after every
     * commit (drag end, resize end, a property-panel change, a dimension
     * change) instead of hand-mirroring the Blade sizing rules in
     * JavaScript, so there's exactly one place that decides how an
     * element looks.
     */
    public function canvasFragment(string $layoutKey): Response
    {
        $this->authorize('viewAny', Product::class);

        $layouts = config('labels.layouts');
        abort_unless(array_key_exists($layoutKey, $layouts), 404);

        $labelLayout = LabelLayout::firstOrCreateForKey($layoutKey);
        $layout = $labelLayout->effectiveLayout($layouts[$layoutKey]);
        $pxPerMm = max(4, min(10, 260 / max($layout['label_w_mm'], $layout['label_h_mm'])));

        return response()->view('admin.products.labels._cell-canvas', [
            'elements'     => $labelLayout->elements->keyBy('type'),
            'cell'         => $this->sampleCell(),
            'pxPerMm'      => $pxPerMm,
            'forceShowAll' => true,
        ]);
    }

    /** Same sample content shown in the designer canvas and its preview iframe. */
    private function sampleCell(): array
    {
        $barcodes = app(BarcodeRenderer::class);
        $sampleCode = '0123456789012';

        return [
            'name'        => __('labels.designer.sample_name'),
            'sku'         => 'SAMPLE-SKU',
            'barcode'     => $sampleCode,
            'price'       => format_money(9.99),
            'barcode_svg' => $barcodes->svg($sampleCode, 1.5, 26),
        ];
    }

    private function deleteStoredImage(LabelLayoutElement $element): void
    {
        $path = $element->config['image_path'] ?? null;
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /** @return array<string, mixed> */
    private function serialize(LabelLayoutElement $element): array
    {
        return [
            'id'          => $element->id,
            'type'        => $element->type,
            'x_pct'       => (float) $element->x_pct,
            'y_pct'       => (float) $element->y_pct,
            'width_pct'   => $element->width_pct !== null ? (float) $element->width_pct : null,
            'height_pct'  => $element->height_pct !== null ? (float) $element->height_pct : null,
            'font_size'   => $element->font_size,
            'font_family' => $element->font_family,
            'align'       => $element->align,
            'scale'       => (float) $element->scale,
            'is_visible'  => $element->is_visible,
            'config'      => $element->config,
        ];
    }
}
