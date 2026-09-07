<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('labels.sheet.title') }}</title>
    {{-- Inline the base stylesheet (print-only page, rendered occasionally). --}}
    <style>{!! file_get_contents(resource_path('css/labels.css')) !!}</style>
    {{-- Per-layout geometry (mm) injected from the chosen layout.
         A `sheet` layout tiles labels across an A4 page; a `roll` layout makes
         the LABEL the page, so a thermal label printer feeds exactly one at a
         time with no sheet margins. --}}
    @php($isRoll = ($layout['type'] ?? 'sheet') === 'roll')
    <style>
        @page {
            size: {{ $isRoll ? $layout['label_w_mm'].'mm '.$layout['label_h_mm'].'mm' : 'A4 portrait' }};
            margin: 0;
        }
        .label-page {
            padding-top: {{ $layout['margin_top_mm'] }}mm;
            padding-left: {{ $layout['margin_left_mm'] }}mm;
        }
        @if ($isRoll)
        /* The page IS the label — override the A4 canvas from labels.css. */
        .label-page {
            width: {{ $layout['label_w_mm'] }}mm;
            min-height: {{ $layout['label_h_mm'] }}mm;
        }
        /* Small labels can't afford a 12mm barcode; scale it to the stock. */
        .label-barcode svg { max-height: {{ round($layout['label_h_mm'] * 0.4, 1) }}mm; }
        @endif
        .label-grid {
            grid-template-columns: repeat({{ (int) $layout['cols'] }}, {{ $layout['label_w_mm'] }}mm);
            column-gap: {{ $layout['col_gap_mm'] }}mm;
            row-gap: {{ $layout['row_gap_mm'] }}mm;
        }
        .label-cell {
            width: {{ $layout['label_w_mm'] }}mm;
            height: {{ $layout['label_h_mm'] }}mm;
        }
    </style>
</head>
<body>
    <div class="label-toolbar" role="toolbar">
        <a href="{{ route('admin.products.labels') }}" class="label-tool-btn">← {{ __('labels.sheet.back') }}</a>
        <span class="label-tool-spacer"></span>
        <span class="label-tool-count">{{ __('labels.sheet.count', ['count' => count($cells)]) }}</span>
        <button type="button" class="label-tool-btn label-tool-btn-primary" onclick="window.print()">
            🖨 {{ __('labels.sheet.print') }}
        </button>
    </div>

    @forelse ($pages as $page)
        <div class="label-page">
            <div class="label-grid">
                @foreach ($page as $cell)
                    <div class="label-cell @if($labelLayout) label-cell--canvas @endif">
                        @if ($labelLayout)
                            @include('admin.products.labels._cell-canvas', ['elements' => $elements, 'cell' => $cell, 'fields' => $fields])
                        @else
                            {{-- Shelf-tag order: name, price, barcode — matches the
                                 physical label stock (name/price band on top,
                                 barcode band below). --}}
                            @if ($fields['name'])
                                <div class="label-name">{{ $cell['name'] }}</div>
                            @endif
                            @if ($fields['price'])
                                <div class="label-price">{{ $cell['price'] }}</div>
                            @endif
                            @if ($fields['sku'])
                                <div class="label-sku">{{ $cell['sku'] }}</div>
                            @endif
                            @if ($fields['barcode'] && $cell['barcode_svg'])
                                <div class="label-barcode">{!! $cell['barcode_svg'] !!}</div>
                                <div class="label-barcode-text">{{ $cell['barcode'] }}</div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="label-page"><p style="padding:20mm;text-align:center;">{{ __('labels.sheet.empty') }}</p></div>
    @endforelse

    @if ($auto)
        <script data-auto-print>
            window.addEventListener('load', () => setTimeout(() => window.print(), 300));
        </script>
    @endif
</body>
</html>
