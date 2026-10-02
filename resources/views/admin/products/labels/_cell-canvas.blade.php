{{--
    One label's content, positioned per a LabelLayout's saved elements
    (percent of the label box). Used by THREE renderers:
      - the real print sheet (sheet.blade.php, looped per cell)
      - the designer's true-size preview iframe (preview.blade.php)
      - the designer's own interactive drag/resize canvas (designer.blade.php)
    all through this one partial, so what the designer shows IS what prints
    — no separate approximate renderer to drift out of sync.

    $elements — Collection<string,LabelLayoutElement> keyed by type.
    $cell     — ['name','sku','price','barcode','barcode_svg']
    $fields   — optional ['name'=>bool,'sku'=>bool,'price'=>bool,'barcode'=>bool];
                when absent, every element with is_visible=true shows
                regardless of the wizard's toggles.
    $pxPerMm  — optional. The print sheet and the true-size preview are real
                mm boxes, so font-size there is literal `pt` (a physical
                unit, correctly proportioned by the browser/printer on its
                own). The designer's drag canvas is a SCALED px box instead
                (zoomed in/out for comfortable dragging — see
                designer.blade.php's $pxPerMm calc), where a literal `pt`
                would NOT scale with that zoom. When set, converts every
                `pt` size to the equivalent px at that same canvas zoom
                (1pt = 0.3528mm) so text reads at the right RELATIVE size
                on the canvas too.
    $forceShowAll — optional. The designer canvas must show every element
                (even is_visible=false ones, dimmed) so the user can select
                and re-enable them; print/preview only ever show visible
                ones.
--}}
@php
    $pxPerMm = $pxPerMm ?? null;
    $forceShowAll = $forceShowAll ?? false;

    $fontStacks = [
        'sans'  => "'Inter', system-ui, -apple-system, sans-serif",
        'serif' => "Georgia, 'Times New Roman', serif",
        'mono'  => "'JetBrains Mono', ui-monospace, monospace",
    ];
    // 1pt = 1/72in = 0.352778mm.
    $fontPx = fn (int $pt) => round($pt * 0.352778 * $pxPerMm, 1).'px';
    $fontSize = fn (?int $pt) => $pt === null ? null : ($pxPerMm ? $fontPx($pt) : $pt.'pt');

    $shouldShow = fn (string $type) =>
        ($forceShowAll || ($elements[$type]->is_visible ?? false))
        && (! isset($fields) || ($fields[$type] ?? true));
    $dimStyle = fn (string $type) => ($forceShowAll && ! ($elements[$type]->is_visible ?? false)) ? 'opacity:.35;' : '';

    // A box big enough to actually contain its content: for name/price/sku
    // that's `width_pct` × `height_pct` when BOTH are set; for barcode/image
    // it's the same, but those two also fall back to the older uniform
    // `scale` transform when no explicit box has been sized yet (every
    // layout seeded before this feature only has `scale`) — so nothing
    // already printing today shifts size until someone actually resizes it.
    $hasBox = fn ($el) => $el->width_pct !== null && $el->height_pct !== null;
@endphp

@if ($shouldShow('name'))
    @php($el = $elements['name'])
    <div class="label-el" data-type="name" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; @if($el->width_pct) width:{{ $el->width_pct }}%; @endif @if($el->height_pct) height:{{ $el->height_pct }}%; display:flex; align-items:center; justify-content:{{ $el->align === 'left' ? 'flex-start' : ($el->align === 'right' ? 'flex-end' : 'center') }}; @endif font-size:{{ $fontSize($el->font_size) }}; font-family:{{ $fontStacks[$el->font_family] ?? $fontStacks['sans'] }}; text-align:{{ $el->align }}; font-weight:600; {{ $dimStyle('name') }}">
        {{ $cell['name'] }}
    </div>
@endif

@if ($shouldShow('price'))
    @php($el = $elements['price'])
    <div class="label-el" data-type="price" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; @if($el->width_pct) width:{{ $el->width_pct }}%; @endif @if($el->height_pct) height:{{ $el->height_pct }}%; display:flex; align-items:center; justify-content:{{ $el->align === 'left' ? 'flex-start' : ($el->align === 'right' ? 'flex-end' : 'center') }}; @endif font-size:{{ $fontSize($el->font_size) }}; font-family:{{ $fontStacks[$el->font_family] ?? $fontStacks['sans'] }}; text-align:{{ $el->align }}; font-weight:700; {{ $dimStyle('price') }}">
        {{ $cell['price'] }}
    </div>
@endif

@if ($shouldShow('sku'))
    @php($el = $elements['sku'])
    <div class="label-el" data-type="sku" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; @if($el->width_pct) width:{{ $el->width_pct }}%; @endif @if($el->height_pct) height:{{ $el->height_pct }}%; display:flex; align-items:center; justify-content:{{ $el->align === 'left' ? 'flex-start' : ($el->align === 'right' ? 'flex-end' : 'center') }}; @endif font-size:{{ $fontSize($el->font_size) }}; font-family:{{ $fontStacks[$el->font_family] ?? $fontStacks['mono'] }}; text-align:{{ $el->align }}; color:#374151; {{ $dimStyle('sku') }}">
        {{ $cell['sku'] }}
    </div>
@endif

@if ($shouldShow('barcode') && $cell['barcode_svg'])
    @php($el = $elements['barcode'])
    @if ($hasBox($el))
        <div class="label-el" data-type="barcode" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; width:{{ $el->width_pct }}%; height:{{ $el->height_pct }}%; display:flex; flex-direction:column; align-items:{{ $el->align === 'left' ? 'flex-start' : ($el->align === 'right' ? 'flex-end' : 'center') }}; {{ $dimStyle('barcode') }}">
            <div class="label-barcode-graphic" style="flex:1 1 auto; min-height:0; width:100%; display:flex; align-items:center; justify-content:center;">{!! $cell['barcode_svg'] !!}</div>
            <div style="flex:0 0 auto; font-size:{{ $fontSize(7) }}; letter-spacing:.5px; font-family:'JetBrains Mono', ui-monospace, monospace; text-align:{{ $el->align }};">{{ $cell['barcode'] }}</div>
        </div>
    @else
        {{-- Legacy sizing — untouched until this element is resized once. --}}
        <div class="label-el" data-type="barcode" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; @if($el->width_pct) width:{{ $el->width_pct }}%; @endif transform: scale({{ $el->scale }}); transform-origin: top {{ $el->align === 'left' ? 'left' : ($el->align === 'right' ? 'right' : 'center') }}; {{ $dimStyle('barcode') }}">
            <div>{!! $cell['barcode_svg'] !!}</div>
            <div style="font-size:{{ $fontSize(7) }}; letter-spacing:.5px; font-family:'JetBrains Mono', ui-monospace, monospace; text-align:{{ $el->align }};">{{ $cell['barcode'] }}</div>
        </div>
    @endif
@endif

@if ($shouldShow('image'))
    @php($el = $elements['image'])
    @php($imagePath = $el->config['image_path'] ?? null)
    @if ($imagePath && $hasBox($el))
        <div class="label-el" data-type="image" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; width:{{ $el->width_pct }}%; height:{{ $el->height_pct }}%; {{ $dimStyle('image') }}">
            <img src="/storage/{{ $imagePath }}" draggable="false" style="display:block; width:100%; height:100%; object-fit:contain; pointer-events:none;">
        </div>
    @elseif ($imagePath)
        {{-- Legacy sizing — untouched until this element is resized once. --}}
        <div class="label-el" data-type="image" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; transform: scale({{ $el->scale }}); transform-origin: top left; {{ $dimStyle('image') }}">
            <img src="/storage/{{ $imagePath }}" draggable="false" style="display:block; max-width:120px; max-height:120px; width:auto; height:auto; pointer-events:none;">
        </div>
    @elseif ($forceShowAll)
        {{-- No logo uploaded yet — the designer still needs a selectable/
             draggable placeholder so the spot can be positioned in advance. --}}
        <div class="label-el" data-type="image" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; width:{{ $el->width_pct ?? 20 }}%; height:{{ $el->height_pct ?? 15 }}%; display:flex; align-items:center; justify-content:center; border:1px dashed #9ca3af; font-size:{{ $fontSize(8) }}; color:#6b7280; {{ $dimStyle('image') }}">
            {{ __('labels.designer.type_image') }}
        </div>
    @endif
@endif
