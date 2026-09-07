@php
    $paperMm = match ($template->paper_size) {
        '58mm' => 58,
        'a4'   => 210,
        default => 80,
    };
    // 203dpi printable width — see CanvasReceiptRasterizer's DOTS_PER_MM
    // docblock for the same unverified-against-real-hardware caveat.
    $printableMm = match ($template->paper_size) {
        '58mm' => 48,
        'a4'   => 200,
        default => 64,
    };

    // Fetched as one array, not per-type via __('...types.'.$type) — several
    // palette types (e.g. `field.date`) are literal keys that contain a dot,
    // and Laravel's translator always treats a dot as nesting regardless of
    // where it came from. __('...types.field.date') goes looking for a
    // nested types→field→date path that was never there, silently falling
    // back to the raw key as the "label" — a real button reading
    // "receipt_templates.canvas.types.field.date". Reading the whole
    // `types` array once and indexing into it with plain PHP array access
    // sidesteps the translator's path-parsing entirely.
    $typeLabels = __('receipt_templates.canvas.types');
@endphp
<x-admin-layout
    active="settings"
    :title="__('receipt_templates.canvas.title', ['name' => $template->name])"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('receipt_templates.title'), 'href' => route('admin.receipt-templates.index')],
        ['label' => $template->name],
    ]">

    <div class="page-wide">
        <div class="page-header mb-4">
            <div>
                <h1 class="page-title">{{ __('receipt_templates.canvas.title', ['name' => $template->name]) }}</h1>
                <p class="page-sub">{{ __('receipt_templates.canvas.sub') }}</p>
                <p class="text-muted text-xs mt-1">
                    {{ __('receipt_templates.canvas.paper_note', ['paper' => __('receipt_templates.paper_size.'.$template->paper_size), 'mm' => $printableMm]) }}
                </p>
                <label class="field-toggle mt-2" style="display:inline-flex; gap:6px;">
                    <span class="field-label" style="margin-bottom:0;">{{ __('receipt_templates.fields.paper_size') }}</span>
                    <select id="rtpl-paper-size" class="pos-input" style="width:auto;">
                        @foreach (['58mm', '80mm', 'a4'] as $size)
                            <option value="{{ $size }}" @selected($template->paper_size === $size)>{{ __('receipt_templates.paper_size.'.$size) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <a href="{{ route('admin.receipt-templates.index') }}" class="pos-btn pos-btn-ghost">{{ __('receipt_templates.canvas.back') }}</a>
        </div>

        <div
            id="rtpl-canvas-app"
            data-template-id="{{ $template->id }}"
            data-paper-mm="{{ $paperMm }}"
            data-printable-mm="{{ $printableMm }}"
            data-paper-size="{{ $template->paper_size }}"
            data-paper-size-url="{{ route('admin.receipt-templates.canvas.paper-size', $template) }}"
            data-store-url="{{ route('admin.receipt-templates.elements.store', $template) }}"
            data-update-url-template="{{ route('admin.receipt-templates.elements.update', [$template, '__ID__']) }}"
            data-destroy-url-template="{{ route('admin.receipt-templates.elements.destroy', [$template, '__ID__']) }}"
            data-preview-url="{{ route('admin.receipt-templates.canvas.preview', $template) }}"
            data-elements="{{ json_encode($elements->map(fn ($e) => [
                'id' => $e->id, 'type' => $e->type, 'x' => (float) $e->x, 'y' => (float) $e->y,
                'width' => $e->width !== null ? (float) $e->width : null,
                'height' => $e->height !== null ? (float) $e->height : null,
                'z_index' => $e->z_index, 'font_size' => $e->font_size, 'font_family' => $e->font_family, 'align' => $e->align,
                'is_bold' => $e->is_bold, 'is_visible' => $e->is_visible, 'config' => $e->config,
            ])) }}"
            data-labels="{{ json_encode([
                'noSelection' => __('receipt_templates.canvas.no_selection'),
                'saving' => __('receipt_templates.canvas.saving'),
                'saved' => __('receipt_templates.canvas.saved'),
                'saveFailed' => __('receipt_templates.canvas.save_failed'),
                'confirmDelete' => __('receipt_templates.canvas.confirm_delete'),
                'types' => __('receipt_templates.canvas.types'),
                'columnsAvailable' => __('receipt_templates.canvas.columns_available'),
            ]) }}"
            class="flex items-start gap-4"
            style="align-items:flex-start;"
        >
            <div style="flex:0 0 180px;">
                <div class="card">
                    <div class="card-body">
                        <strong class="text-xs text-muted" style="display:block; margin-bottom:8px;">{{ __('receipt_templates.canvas.palette') }}</strong>
                        <div id="rtpl-palette" style="display:flex; flex-direction:column; gap:6px;">
                            @foreach ($paletteTypes as $type)
                                <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" data-add-type="{{ $type }}" style="justify-content:flex-start;">
                                    {{ $typeLabels[$type] ?? $type }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div style="flex:0 0 auto;">
                <div id="rtpl-canvas-surface" style="position:relative; background:#fff; border:1px solid var(--pos-border,#e5e7eb); box-shadow:0 1px 3px rgba(0,0,0,.08); overflow:visible;">
                    <div id="rtpl-printable-guide" style="position:absolute; top:0; bottom:0; left:0; border-right:1px dashed #f59e0b; pointer-events:none;"></div>
                </div>
                <div id="rtpl-save-status" class="text-xs text-muted mt-2"></div>
            </div>

            <div style="flex:1 1 0; min-width:260px; max-width:340px; position:sticky; top:16px;">
                <div class="card">
                    <div class="card-body">
                        <strong class="text-xs text-muted" style="display:block; margin-bottom:8px;">{{ __('receipt_templates.blocks.preview') ?? 'Preview' }}</strong>
                        <div id="rtpl-property-panel"></div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-body" style="padding:0; overflow:hidden; border-radius:8px;">
                        <iframe id="rtpl-preview-frame" src="{{ route('admin.receipt-templates.canvas.preview', $template) }}"
                                style="width:100%; height:50vh; border:0;" title="{{ __('receipt_templates.canvas.preview') }}"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/admin/receipt-canvas-editor.js'])
</x-admin-layout>
