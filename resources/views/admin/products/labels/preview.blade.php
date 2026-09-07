<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('labels.designer.preview') }}</title>
    <style>{!! file_get_contents(resource_path('css/labels.css')) !!}</style>
    <style>
        html, body { background: #e5e7eb; }
        .label-page { margin: 16px auto; box-shadow: 0 1px 6px rgba(0,0,0,.15); width: auto; min-height: 0; }
        .label-cell {
            width: {{ $layout['label_w_mm'] }}mm;
            height: {{ $layout['label_h_mm'] }}mm;
            margin: 12px;
        }
        .label-barcode svg { max-height: {{ round($layout['label_h_mm'] * 0.4, 1) }}mm; }
    </style>
</head>
<body>
    <div class="label-page">
        <div class="label-cell label-cell--canvas">
            @include('admin.products.labels._cell-canvas', ['elements' => $elements, 'cell' => $cell])
        </div>
    </div>
</body>
</html>
