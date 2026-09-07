@php
    // Editable px/mm scale for the canvas — small labels (30x20mm) still
    // need to be comfortably drag-able, so clamp to a sane minimum.
    $pxPerMm = max(4, min(10, 260 / max($layout['label_w_mm'], $layout['label_h_mm'])));
    $canvasW = round($layout['label_w_mm'] * $pxPerMm);
    $canvasH = round($layout['label_h_mm'] * $pxPerMm);

    $typeLabels = [
        'name'    => __('labels.designer.type_name'),
        'sku'     => __('labels.designer.type_sku'),
        'price'   => __('labels.designer.type_price'),
        'barcode' => __('labels.designer.type_barcode'),
        'image'   => __('labels.designer.type_image'),
    ];
@endphp
<x-admin-layout
    active="product-labels"
    :title="__('labels.designer.title', ['layout' => $layout['label']])"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('labels.crumb_parent'), 'href' => '/admin/products'],
        ['label' => __('labels.title'), 'href' => route('admin.products.labels')],
        ['label' => $layout['label']],
    ]">

    <div class="page-wide">
        <div class="page-header mb-4">
            <div>
                <h1 class="page-title">{{ __('labels.designer.title', ['layout' => $layout['label']]) }}</h1>
                <p class="page-sub">{{ __('labels.designer.sub') }}</p>
            </div>
            <a href="{{ route('admin.products.labels') }}" class="pos-btn pos-btn-ghost">{{ __('labels.designer.back') }}</a>
        </div>

        <div
            id="lbl-canvas-app"
            data-layout-key="{{ $layoutKey }}"
            data-canvas-w="{{ $canvasW }}"
            data-canvas-h="{{ $canvasH }}"
            data-update-url-template="{{ route('admin.products.labels.designer.update', [$layoutKey, '__TYPE__']) }}"
            data-preview-url="{{ route('admin.products.labels.designer.preview', $layoutKey) }}"
            data-elements="{{ json_encode($elements->map(fn ($e) => [
                'type' => $e->type, 'x_pct' => (float) $e->x_pct, 'y_pct' => (float) $e->y_pct,
                'width_pct' => $e->width_pct !== null ? (float) $e->width_pct : null,
                'font_size' => $e->font_size, 'font_family' => $e->font_family, 'align' => $e->align,
                'scale' => (float) $e->scale, 'is_visible' => $e->is_visible, 'config' => $e->config,
            ])->values()) }}"
            data-labels="{{ json_encode([
                'noSelection' => __('labels.designer.no_selection'),
                'saving' => __('labels.designer.saving'),
                'saved' => __('labels.designer.saved'),
                'saveFailed' => __('labels.designer.save_failed'),
                'types' => $typeLabels,
            ]) }}"
            class="flex items-start gap-4"
            style="align-items:flex-start;"
        >
            <div style="flex:0 0 180px;">
                <div class="card">
                    <div class="card-body">
                        <strong class="text-xs text-muted" style="display:block; margin-bottom:8px;">{{ __('labels.designer.elements') }}</strong>
                        <div id="lbl-palette" style="display:flex; flex-direction:column; gap:6px;">
                            @foreach ($typeLabels as $type => $label)
                                <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" data-select-type="{{ $type }}" style="justify-content:flex-start;">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div style="flex:0 0 auto;">
                <div id="lbl-canvas-surface" style="position:relative; background:#fff; border:1px solid var(--pos-border,#e5e7eb); box-shadow:0 1px 3px rgba(0,0,0,.08); width:{{ $canvasW }}px; height:{{ $canvasH }}px;"></div>
                <div id="lbl-save-status" class="text-xs text-muted mt-2"></div>
            </div>

            <div style="flex:1 1 0; min-width:260px; max-width:340px; position:sticky; top:16px;">
                <div class="card">
                    <div class="card-body">
                        <strong class="text-xs text-muted" style="display:block; margin-bottom:8px;">{{ __('labels.designer.preview') }}</strong>
                        <div id="lbl-property-panel"></div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-body" style="padding:0; overflow:hidden; border-radius:8px;">
                        <iframe id="lbl-preview-frame" src="{{ route('admin.products.labels.designer.preview', $layoutKey) }}"
                                style="width:100%; height:40vh; border:0;" title="{{ __('labels.designer.preview') }}"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/admin/label-canvas-editor.js'])
</x-admin-layout>
