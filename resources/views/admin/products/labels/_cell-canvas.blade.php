{{--
    One label's content, positioned per a LabelLayout's saved elements
    (percent of the label box). Used both by the real print sheet
    (sheet.blade.php, looped per cell) and the designer's single-sample
    preview (preview.blade.php).

    $elements — Collection<string,LabelLayoutElement> keyed by type.
    $cell     — ['name','sku','price','barcode','barcode_svg']
    $fields   — optional ['name'=>bool,'sku'=>bool,'price'=>bool,'barcode'=>bool];
                when absent (the designer preview), every element with
                is_visible=true shows regardless of the wizard's toggles.
--}}
@php
    $fontStacks = [
        'sans'  => "'Inter', system-ui, -apple-system, sans-serif",
        'serif' => "Georgia, 'Times New Roman', serif",
        'mono'  => "'JetBrains Mono', ui-monospace, monospace",
    ];
    $shouldShow = fn (string $type) => ($elements[$type]->is_visible ?? false) && (! isset($fields) || ($fields[$type] ?? true));
@endphp

@if ($shouldShow('name'))
    @php($el = $elements['name'])
    <div class="label-el" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; @if($el->width_pct) width:{{ $el->width_pct }}%; @endif font-size:{{ $el->font_size }}pt; font-family:{{ $fontStacks[$el->font_family] ?? $fontStacks['sans'] }}; text-align:{{ $el->align }}; font-weight:600;">
        {{ $cell['name'] }}
    </div>
@endif

@if ($shouldShow('price'))
    @php($el = $elements['price'])
    <div class="label-el" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; @if($el->width_pct) width:{{ $el->width_pct }}%; @endif font-size:{{ $el->font_size }}pt; font-family:{{ $fontStacks[$el->font_family] ?? $fontStacks['sans'] }}; text-align:{{ $el->align }}; font-weight:700;">
        {{ $cell['price'] }}
    </div>
@endif

@if ($shouldShow('sku'))
    @php($el = $elements['sku'])
    <div class="label-el" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; @if($el->width_pct) width:{{ $el->width_pct }}%; @endif font-size:{{ $el->font_size }}pt; font-family:{{ $fontStacks[$el->font_family] ?? $fontStacks['mono'] }}; text-align:{{ $el->align }}; color:#374151;">
        {{ $cell['sku'] }}
    </div>
@endif

@if ($shouldShow('barcode') && $cell['barcode_svg'])
    @php($el = $elements['barcode'])
    <div class="label-el" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; @if($el->width_pct) width:{{ $el->width_pct }}%; @endif transform: scale({{ $el->scale }}); transform-origin: top {{ $el->align === 'left' ? 'left' : ($el->align === 'right' ? 'right' : 'center') }};">
        <div>{!! $cell['barcode_svg'] !!}</div>
        <div style="font-size:7pt; letter-spacing:.5px; font-family:'JetBrains Mono', ui-monospace, monospace; text-align:{{ $el->align }};">{{ $cell['barcode'] }}</div>
    </div>
@endif

@if ($shouldShow('image') && ($elements['image']->config['image_path'] ?? null))
    @php($el = $elements['image'])
    <div class="label-el" style="left:{{ $el->x_pct }}%; top:{{ $el->y_pct }}%; transform: scale({{ $el->scale }}); transform-origin: top left;">
        <img src="/storage/{{ $el->config['image_path'] }}" style="display:block; max-width:120px; max-height:120px; width:auto; height:auto;">
    </div>
@endif
