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
            'layout'      => $layouts[$layoutKey],
            'labelLayout' => $labelLayout,
            'elements'    => $labelLayout->elements->keyBy('type'),
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
            'font_size'   => ['sometimes', 'integer', 'min:5', 'max:48'],
            'font_family' => ['sometimes', 'in:'.implode(',', LabelLayoutElement::FONTS)],
            'align'       => ['sometimes', 'in:left,center,right'],
            'scale'       => ['sometimes', 'numeric', 'min:0.1', 'max:3'],
            'is_visible'  => ['sometimes', 'boolean'],
            'image'        => ['sometimes', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'image_remove' => ['sometimes', 'boolean'],
        ]);

        $update = array_intersect_key($data, array_flip(['x_pct', 'y_pct', 'width_pct', 'font_size', 'font_family', 'align', 'scale', 'is_visible']));

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
        $layout = $layouts[$layoutKey];

        $barcodes = app(BarcodeRenderer::class);
        $sampleCode = '0123456789012';
        $cell = [
            'name'        => __('labels.designer.sample_name'),
            'sku'         => 'SAMPLE-SKU',
            'barcode'     => $sampleCode,
            'price'       => format_money(9.99),
            'barcode_svg' => $barcodes->svg($sampleCode, 1.5, 26),
        ];

        return response()->view('admin.products.labels.preview', [
            'layout'   => $layout,
            'cell'     => $cell,
            'elements' => $labelLayout->elements->keyBy('type'),
        ]);
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
            'font_size'   => $element->font_size,
            'font_family' => $element->font_family,
            'align'       => $element->align,
            'scale'       => (float) $element->scale,
            'is_visible'  => $element->is_visible,
            'config'      => $element->config,
        ];
    }
}
