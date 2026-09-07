@php
    // Physical paper width in mm — the canonical unit for every element's
    // x/y/width (same unit CanvasReceiptRasterizer uses for the ESC/POS
    // side, so the two renderers agree on where things sit).
    $canvasWidthMm = match ($template->paper_size) {
        '58mm' => 58,
        'a4'   => 210,
        default => 80,
    };
    $rowHeightMm = 4.5;
    // Sub-lines (barcode/SKU/discount/batch, printed under an item's own
    // row) sit closer to their item than a full row would — a smaller
    // step, not the same spacing used between two separate items.
    // Default sub-line (SKU/barcode/discount/batch) step, overridable per
    // items_table via config.subline_gap_mm — same knob and default as
    // CanvasReceiptRasterizer::sublineGapMm().
    $sublineGapMmFor = fn ($el) => (float) ($el->config['subline_gap_mm'] ?? 3.0);

    $plainMoney = function ($amount) {
        $c = app_currency();

        return number_format((float) ($amount ?? 0), $c['decimals'], $c['decimal_separator'] ?: '.', $c['thousands_separator']);
    };
    $cellValue = function ($item, string $field, bool $showCurrency = true) use ($plainMoney) {
        return match ($field) {
            'name'       => $item->product_name_snapshot ?: $item->product?->name,
            'sku'        => $item->sku_snapshot ?: $item->product?->sku ?: '',
            'hsn'        => $item->hsn_snapshot ?: $item->product?->hsn_code ?: '',
            'qty'        => rtrim(rtrim((string) $item->quantity, '0'), '.') ?: '0',
            'unit_price' => $showCurrency ? format_money($item->unit_price) : $plainMoney($item->unit_price),
            'line_total' => $showCurrency ? format_money($item->line_total) : $plainMoney($item->line_total),
            default      => '',
        };
    };
    // The actual cell <div>s in the items_table branch below get a real
    // CSS width and wrap natively — the browser measures real glyphs, so
    // that part is exact. This estimate exists only to advance $rowY (an
    // absolutely-positioned layout has no other way to know how much
    // taller a wrapped row got) — a rough miss here means a slightly off
    // gap in the PREVIEW, not the real print, which uses
    // CanvasReceiptRasterizer's own GD-measured wrap.
    $estimateLines = function (string $text, float $widthMm, int $fontSize) {
        $text = trim($text);
        if ($text === '') return 0;
        $charsPerLine = max(4, (int) floor($widthMm * 5.0 / max(6, $fontSize)));
        return count(explode("\n", wordwrap($text, $charsPerLine, "\n", true)));
    };

    $fieldValue = function (string $type) use ($sale, $company) {
        return match ($type) {
            'field.date'          => format_date($sale->sale_datetime ?? $sale->created_at),
            'field.time'          => format_time($sale->sale_datetime ?? $sale->created_at),
            'field.sale_number'   => $sale->number,
            'field.customer_name' => $sale->customer?->name ? 'Customer: '.$sale->customer->name : '',
            'field.cashier_name'  => $sale->cashier?->name ?? '',
            'field.store_name'    => $sale->store?->name ?? '',
            'field.store_address' => implode(', ', array_filter([
                $sale->store?->address_line1, $sale->store?->address_line2, $sale->store?->city,
            ])),
            'field.store_phone'   => $sale->store?->phone ?? '',
            'field.grand_total'   => format_money($sale->grand_total),
            'field.tax_total'     => format_money($sale->tax_total),
            'field.item_count'    => (string) $sale->items->count(),
            'field.return_policy' => (string) ($company->receipt_return_policy ?? ''),
            default               => '',
        };
    };

    // Same font files the ESC/POS compositor draws with (CanvasReceiptRasterizer::FONT_FILES),
    // self-hosted via @font-face so the browser preview matches what actually prints —
    // a system font substitute would drift from the real thermal output.
    $fontFamilyCss = fn (string $key) => match ($key) {
        'dejavu_serif' => "'DejaVu Serif', serif",
        'dejavu_mono'  => "'DejaVu Sans Mono', monospace",
        default        => "'DejaVu Sans', sans-serif",
    };

    // Elements sit at fixed x/y chosen once at design time — no document
    // flow. items_table, totals, and payments all have a REAL height that
    // varies per sale (item count; whether discount/tax/rounding lines
    // apply; tender count) — anything positioned below one of those
    // cascades down by however much taller it turned out than what the
    // admin designed around (`height`, if set, is that baseline;
    // otherwise a sane per-type default), plus whatever already
    // accumulated from earlier dynamic-height elements above it. Same
    // math as CanvasReceiptRasterizer::render() so the HTML preview
    // matches what actually prints. Every other element type keeps
    // designed == actual, so it never contributes a shift.
    $sortedElements = $template->visibleElements()->sortBy('y')->values();

    $itemsTableActualHeightMm = function ($el) use ($sale, $rowHeightMm, $sublineGapMmFor, $estimateLines, $cellValue) {
        $columns = $el->config['columns'] ?? [
            ['field' => 'name', 'label' => 'Item', 'width_mm' => 20],
            ['field' => 'qty', 'label' => 'Qty', 'width_mm' => 8],
            ['field' => 'unit_price', 'label' => 'Price', 'width_mm' => 10],
            ['field' => 'line_total', 'label' => 'Total', 'width_mm' => 10],
        ];
        $showCurrency = $el->config['show_currency'] ?? true;
        $height = $rowHeightMm + 1.5; // header + gap before first item row
        foreach ($sale->items as $item) {
            $rowLines = 1;
            foreach ($columns as $col) {
                $rowLines = max($rowLines, $estimateLines($cellValue($item, $col['field'] ?? 'name', $showCurrency), (float) ($col['width_mm'] ?? 20) - 2, $el->font_size) ?: 1);
            }
            $height += $rowLines * $rowHeightMm;
            $sku = $item->sku_snapshot ?: $item->product?->sku;
            $barcode = $item->barcode_snapshot ?: $item->product?->barcode;
            $sublineGapMm = $sublineGapMmFor($el);
            if (! empty($el->config['show_sku_subline']) && ($sku || $barcode)) $height += $sublineGapMm;
            if (! empty($el->config['show_barcode_subline']) && $barcode) $height += $sublineGapMm;
            if (! empty($el->config['show_discount_subline']) && ((float) ($item->discount_amount ?? 0) > 0 || (float) ($item->discount_percent ?? 0) > 0)) $height += $sublineGapMm;
            if (! empty($el->config['show_batch_subline']) && $item->batch) $height += $sublineGapMm;
        }
        return $height;
    };
    $totalsRowCount = 3; // Subtotal, Tax (always shown), Grand Total
    if ((float) ($sale->discount_total ?? 0) > 0) $totalsRowCount++;
    if ((float) ($sale->additional_charges_total ?? 0) > 0) $totalsRowCount++;
    if ((float) ($sale->rounding_adjustment ?? 0) != 0) $totalsRowCount++;
    $paymentsRowCount = max(1, $sale->payments->count() + ((float) $sale->change_returned > 0 ? 1 : 0));
    // Same font-proportional row step CanvasReceiptRasterizer::rowStepDots()
    // uses — the flat $rowHeightMm (tuned for the items table's own rows)
    // made a 5-6 row totals block look badly double-spaced at typical font
    // sizes; this keeps the preview's shift estimate consistent with what
    // actually prints.
    $rowStepMm = fn (int $fontSize) => ($fontSize + 8) / (203 / 25.4);
    // A bit more breathing room than the item table's own rows specifically
    // for totals/payments — matches CanvasReceiptRasterizer::totalsRowStepDots().
    $totalsRowStepMm = fn (int $fontSize) => $rowStepMm($fontSize) + (6 / (203 / 25.4));

    // Real row text, so a narrow admin-resized totals/payments box (which
    // wraps in the browser exactly like any other width-bound div) is
    // accounted for here too — otherwise the shift estimate undercounts
    // and whatever's positioned below ends up overlapping the wrapped line.
    $grossSubtotalForEstimate = bcadd(bcadd((string) $sale->subtotal, (string) $sale->tax_total, 4), (string) $sale->discount_total, 4);
    $totalsRowTexts = ['Subtotal: '.format_money($grossSubtotalForEstimate)];
    if ((float) ($sale->discount_total ?? 0) > 0) $totalsRowTexts[] = 'Discount: -'.format_money($sale->discount_total);
    $totalsRowTexts[] = 'Tax (incl.): '.format_money($sale->tax_total); // always shown, even at zero
    if ((float) ($sale->additional_charges_total ?? 0) > 0) $totalsRowTexts[] = 'Additional charges: '.format_money($sale->additional_charges_total);
    if ((float) ($sale->rounding_adjustment ?? 0) != 0) $totalsRowTexts[] = 'Rounding: '.format_money($sale->rounding_adjustment);
    $totalsRowTexts[] = 'Total: '.format_money($sale->grand_total);

    $paymentsRowTexts = $sale->payments->map(fn ($p) => ($p->paymentMethod?->name ?? $p->method_code).': '.format_money($p->amount))->all();
    if ((float) $sale->change_returned > 0) $paymentsRowTexts[] = 'Change: '.format_money($sale->change_returned);

    $wrappedLineTotal = fn (array $texts, $el) => array_sum(array_map(
        fn ($t) => $el->width ? max(1, $estimateLines($t, (float) $el->width, $el->font_size)) : 1,
        $texts,
    ));

    $shiftForId = [];
    $cumulativeShiftMm = 0.0;
    foreach ($sortedElements as $el) {
        $shiftForId[$el->id] = $cumulativeShiftMm;
        // A width-bound custom text block that wraps to more than one
        // line is the same "grew taller than designed" case as the
        // others — mirrors CanvasReceiptRasterizer::designedHeightMm()'s
        // one-line baseline so a long divider/label doesn't silently
        // overlap whatever's positioned right below it.
        $textLines = ($el->type === 'text' && $el->width)
            ? max(1, $estimateLines((string) ($el->config['text'] ?? ''), (float) $el->width, $el->font_size))
            : 1;
        $actual = match ($el->type) {
            'items_table' => $itemsTableActualHeightMm($el),
            'totals'      => $wrappedLineTotal($totalsRowTexts, $el) * $totalsRowStepMm($el->font_size),
            'payments'    => $wrappedLineTotal($paymentsRowTexts, $el) * $totalsRowStepMm($el->font_size),
            'text'        => $el->width ? $textLines * $rowStepMm($el->font_size) : 0.0,
            default       => 0.0,
        };
        $designed = $el->height !== null ? (float) $el->height : match ($el->type) {
            'items_table' => 2 * $rowHeightMm,
            'text'        => $el->width ? $rowStepMm($el->font_size) : $actual,
            'totals'      => 2 * $totalsRowStepMm($el->font_size),
            'payments'    => $totalsRowStepMm($el->font_size),
            default       => $actual,
        };
        $cumulativeShiftMm += max(0.0, $actual - $designed);
    }
    $shiftFor = fn ($el) => $shiftForId[$el->id] ?? 0.0;
@endphp
<style>
    @font-face { font-family: 'DejaVu Sans'; src: url('{{ asset('fonts/DejaVuSans.ttf') }}') format('truetype'); }
    @font-face { font-family: 'DejaVu Serif'; src: url('{{ asset('fonts/DejaVuSerif.ttf') }}') format('truetype'); }
    @font-face { font-family: 'DejaVu Sans Mono'; src: url('{{ asset('fonts/DejaVuSansMono.ttf') }}') format('truetype'); }
</style>
<div class="rcpt-canvas" style="position:relative; width:{{ $canvasWidthMm }}mm; margin:0 auto; font-family:'DejaVu Sans', sans-serif;">
    @foreach ($sortedElements as $el)
        @php
            $elY = (float) $el->y + $shiftFor($el);
            $style = 'position:absolute; left:'.$el->x.'mm; top:'.$elY.'mm;'
                .($el->width ? ' width:'.$el->width.'mm;' : '')
                .' font-size:'.$el->font_size.'pt;'
                .' font-family:'.$fontFamilyCss($el->font_family).';'
                .' text-align:'.$el->align.';'
                .($el->is_bold ? ' font-weight:bold;' : '')
                .' white-space:pre-wrap;';
        @endphp

        @if ($el->type === 'text')
            <div style="{{ $style }}" dir="auto">{{ $el->config['text'] ?? '' }}</div>

        @elseif ($el->type === 'image')
            @if (! empty($el->config['image_path']))
                <img src="{{ \Illuminate\Support\Facades\Storage::url($el->config['image_path']) }}" alt=""
                     style="position:absolute; left:{{ $el->x }}mm; top:{{ $elY }}mm;{{ $el->width ? ' width:'.$el->width.'mm;' : '' }}">
            @endif

        @elseif ($el->type === 'logo')
            @if ($company->logo_url)
                <img src="{{ $company->logo_url }}" alt=""
                     style="position:absolute; left:{{ $el->x }}mm; top:{{ $elY }}mm;{{ $el->width ? ' width:'.$el->width.'mm;' : '' }}">
            @endif

        @elseif ($el->type === 'barcode')
            <div style="position:absolute; left:{{ $el->x }}mm; top:{{ $elY }}mm;">
                <x-receipt-barcode :value="$sale->number" />
            </div>

        @elseif ($el->type === 'qr')
            @if ($receiptUrl)
                <div style="position:absolute; left:50%; top:{{ $elY }}mm; transform:translateX(-50%);">
                    <x-receipt-qr :url="$receiptUrl" />
                </div>
            @endif

        @elseif ($el->type === 'totals')
            {{-- Same conditional breakdown the legacy Company-field-driven receipt always showed — a row only appears when it actually applies to this sale. --}}
            @php
                // Sale::subtotal is POST-discount, PRE-tax (subtotal + tax_total ==
                // grand_total) — showing it raw as "Subtotal" here while also showing
                // a Discount row below would double-count the discount visually.
                // Reconstruct the pre-discount gross figure, same as EscPosFormatter.
                $grossSubtotal = bcadd(bcadd((string) $sale->subtotal, (string) $sale->tax_total, 4), (string) $sale->discount_total, 4);
            @endphp
            <div style="{{ $style }}">
                <div>{{ __('sales.receipt.subtotal') }}: {{ format_money($grossSubtotal) }}</div>
                @if ((float) ($sale->discount_total ?? 0) > 0)
                    <div>{{ __('sales.receipt.discount') }}: -{{ format_money($sale->discount_total) }}</div>
                @endif
                <div>{{ __('sales.receipt.tax_already_included') }}: {{ format_money($sale->tax_total) }}</div>
                @if ((float) ($sale->additional_charges_total ?? 0) > 0)
                    <div>{{ __('sales.receipt.charges') }}: {{ format_money($sale->additional_charges_total) }}</div>
                @endif
                @if ((float) ($sale->rounding_adjustment ?? 0) != 0)
                    <div>{{ __('sales.receipt.rounding') }}: {{ format_money($sale->rounding_adjustment) }}</div>
                @endif
                <div style="font-weight:bold;">{{ __('sales.receipt.grand_total') }}: {{ format_money($sale->grand_total) }}</div>
            </div>

        @elseif ($el->type === 'payments')
            <div style="{{ $style }}">
                @foreach ($sale->payments as $payment)
                    <div>{{ $payment->paymentMethod?->name ?? $payment->method_code }}: {{ format_money($payment->amount) }}</div>
                @endforeach
                @if ((float) $sale->change_returned > 0)
                    <div>{{ __('sales.receipt.change') }}: {{ format_money($sale->change_returned) }}</div>
                @endif
            </div>

        @elseif ($el->type === 'items_table')
            @php
                $columns = $el->config['columns'] ?? [
                    ['field' => 'name', 'label' => 'Item', 'width_mm' => 20],
                    ['field' => 'qty', 'label' => 'Qty', 'width_mm' => 8],
                    ['field' => 'unit_price', 'label' => 'Price', 'width_mm' => 10],
                    ['field' => 'line_total', 'label' => 'Total', 'width_mm' => 10],
                ];
                $colX = [];
                $cursor = (float) $el->x;
                foreach ($columns as $col) {
                    $colX[] = $cursor;
                    $cursor += (float) ($col['width_mm'] ?? 20);
                }
                // $cellValue and $estimateLines are defined once, at the top of the
                // file — reused here for the actual cell markup, and up there for
                // the auto-shift height estimate, so the two never drift apart.
                $discountLabel = function ($item) {
                    if ((float) ($item->discount_amount ?? 0) > 0) {
                        return __('sales.receipt.discount').': -'.format_money($item->discount_amount);
                    }
                    if ((float) ($item->discount_percent ?? 0) > 0) {
                        return __('sales.receipt.discount').': -'.rtrim(rtrim((string) $item->discount_percent, '0'), '.').'%';
                    }
                    return '';
                };
                // SKU preferred, barcode as fallback — same snapshot-then-live fallback pattern used everywhere else on the receipt.
                $skuOrBarcode = function ($item) {
                    $sku = $item->sku_snapshot ?: $item->product?->sku;
                    if ($sku) return __('sales.receipt.sku').': '.$sku;
                    return (string) ($item->barcode_snapshot ?: $item->product?->barcode ?: '');
                };
                // Barcode always, regardless of whether a SKU exists — distinct
                // from $skuOrBarcode above, which prefers SKU. A template picks
                // one toggle or the other depending on which code the business
                // actually wants printed on the receipt. Printed bare (no
                // "Barcode:" label) — the digits are unambiguous on their own.
                $barcodeOnly = function ($item) {
                    return (string) ($item->barcode_snapshot ?: $item->product?->barcode ?: '');
                };
                $rowY = (float) $el->y;
                $tableTopMm = $rowY;
                $tableWidthMm = $cursor - (float) $el->x;
                $showBorders = ! empty($el->config['show_borders']);
                $showCurrency = $el->config['show_currency'] ?? true;
                $hLines = []; // y-positions (mm) of each horizontal divider, filled in below
                // The actual cell <div>s below get a real CSS width and wrap
                // natively — the browser measures real glyphs, so that part
                // is exact. This estimate exists only to advance $rowY (an
                // absolutely-positioned layout has no other way to know how
                // much taller a wrapped row got) — a rough miss here means
                // a slightly off gap in the PREVIEW, not the real print,
                // which uses CanvasReceiptRasterizer's own GD-measured wrap.
                $estimateLines = function (string $text, float $widthMm, int $fontSize) {
                    $text = trim($text);
                    if ($text === '') return 0;
                    $charsPerLine = max(4, (int) floor($widthMm * 5.0 / max(6, $fontSize)));
                    return count(explode("\n", wordwrap($text, $charsPerLine, "\n", true)));
                };
            @endphp
            @foreach ($columns as $i => $col)
                <div style="position:absolute; left:{{ $colX[$i] }}mm; top:{{ $rowY }}mm; width:{{ (float) ($col['width_mm'] ?? 20) - 2 }}mm; font-size:{{ $el->font_size }}pt; font-family:{{ $fontFamilyCss($el->font_family) }}; font-weight:bold; padding:0 1mm; white-space:normal; word-break:break-word;">
                    {{ $col['label'] ?? '' }}
                </div>
            @endforeach
            @php $rowY += $rowHeightMm; $hLines[] = $rowY; $rowY += 1.5; @endphp
            @foreach ($sale->items as $item)
                @php
                    $rowLines = 1;
                    foreach ($columns as $col) {
                        $rowLines = max($rowLines, $estimateLines($cellValue($item, $col['field'] ?? 'name', $showCurrency), (float) ($col['width_mm'] ?? 20) - 2, $el->font_size) ?: 1);
                    }
                @endphp
                @foreach ($columns as $i => $col)
                    <div style="position:absolute; left:{{ $colX[$i] }}mm; top:{{ $rowY }}mm; width:{{ (float) ($col['width_mm'] ?? 20) - 2 }}mm; font-size:{{ $el->font_size }}pt; font-family:{{ $fontFamilyCss($el->font_family) }}; padding:0 1mm; white-space:normal; word-break:break-word;">
                        {{ $cellValue($item, $col['field'] ?? 'name', $showCurrency) }}
                    </div>
                @endforeach
                @php $rowY += $rowLines * $rowHeightMm; @endphp

                @if (! empty($el->config['show_sku_subline']) && $skuOrBarcode($item) !== '')
                    @php $subLines = max(1, $estimateLines($skuOrBarcode($item), $tableWidthMm - 2, max(7, $el->font_size - 2))); @endphp
                    <div style="position:absolute; left:{{ $el->x }}mm; top:{{ $rowY }}mm; width:{{ $tableWidthMm - 2 }}mm; font-size:{{ max(7, $el->font_size - 2) }}pt; font-family:{{ $fontFamilyCss($el->font_family) }}; color:#666; white-space:normal; word-break:break-word;">
                        {{ $skuOrBarcode($item) }}
                    </div>
                    @php $rowY += $subLines * $sublineGapMmFor($el); @endphp
                @endif
                @if (! empty($el->config['show_barcode_subline']) && $barcodeOnly($item) !== '')
                    @php $subLines = max(1, $estimateLines($barcodeOnly($item), $tableWidthMm - 2, max(7, $el->font_size - 2))); @endphp
                    <div style="position:absolute; left:{{ $el->x }}mm; top:{{ $rowY }}mm; width:{{ $tableWidthMm - 2 }}mm; font-size:{{ max(7, $el->font_size - 2) }}pt; font-family:{{ $fontFamilyCss($el->font_family) }}; color:#666; white-space:normal; word-break:break-word;">
                        {{ $barcodeOnly($item) }}
                    </div>
                    @php $rowY += $subLines * $sublineGapMmFor($el); @endphp
                @endif
                @if (! empty($el->config['show_discount_subline']) && $discountLabel($item) !== '')
                    @php $subLines = max(1, $estimateLines($discountLabel($item), $tableWidthMm - 2, max(7, $el->font_size - 2))); @endphp
                    <div style="position:absolute; left:{{ $el->x }}mm; top:{{ $rowY }}mm; width:{{ $tableWidthMm - 2 }}mm; font-size:{{ max(7, $el->font_size - 2) }}pt; font-family:{{ $fontFamilyCss($el->font_family) }}; color:#666; white-space:normal; word-break:break-word;">
                        {{ $discountLabel($item) }}
                    </div>
                    @php $rowY += $subLines * $sublineGapMmFor($el); @endphp
                @endif
                @if (! empty($el->config['show_batch_subline']) && $item->batch)
                    <div style="position:absolute; left:{{ $el->x }}mm; top:{{ $rowY }}mm; font-size:{{ max(7, $el->font_size - 2) }}pt; font-family:{{ $fontFamilyCss($el->font_family) }}; color:#666;">
                        Batch {{ $item->batch->batch_number }}
                    </div>
                    @php $rowY += $sublineGapMmFor($el); @endphp
                @endif
                @php $hLines[] = $rowY; @endphp
            @endforeach

            @if ($showBorders)
                {{-- Outer box + column dividers, spanning the full table height. --}}
                <div style="position:absolute; left:{{ $el->x }}mm; top:{{ $tableTopMm }}mm; width:{{ $tableWidthMm }}mm; height:{{ $rowY - $tableTopMm }}mm; border:1px solid #000;"></div>
                @for ($i = 1; $i < count($colX); $i++)
                    <div style="position:absolute; left:{{ $colX[$i] }}mm; top:{{ $tableTopMm }}mm; width:0; height:{{ $rowY - $tableTopMm }}mm; border-left:1px solid #000;"></div>
                @endfor
                {{-- Horizontal dividers: under the header, and under each item's own block (row + its sublines). --}}
                @foreach ($hLines as $lineY)
                    <div style="position:absolute; left:{{ $el->x }}mm; top:{{ $lineY }}mm; width:{{ $tableWidthMm }}mm; height:0; border-top:1px solid #000;"></div>
                @endforeach
            @elseif (! empty($el->config['show_row_dividers']))
                {{-- Row separators only, no outer box / column lines — the lighter-weight option. --}}
                @foreach ($hLines as $lineY)
                    <div style="position:absolute; left:{{ $el->x }}mm; top:{{ $lineY }}mm; width:{{ $tableWidthMm }}mm; height:0; border-top:1px solid #000;"></div>
                @endforeach
            @endif

        @elseif ($el->isFieldPlaceholder())
            <div style="{{ $style }}" dir="auto">{{ $fieldValue($el->type) }}</div>
        @endif
    @endforeach
</div>
