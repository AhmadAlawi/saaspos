<?php

namespace App\Services\Hardware;

use App\Models\Company;
use App\Models\ReceiptTemplate;
use App\Models\ReceiptTemplateElement;
use App\Models\Sale;
use ArPHP\I18N\Arabic;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Renders a "Canvas"-mode {@see ReceiptTemplate} (free x/y positioned
 * {@see ReceiptTemplateElement} rows) as ONE rasterized bitmap, then hands
 * it to {@see RasterToEscPos::encodeImage()} — the same `GS v 0` command
 * already used for the logo and for Arabic text. This is the only way to
 * get true pixel positioning on a thermal printer: ESC/POS text mode is a
 * fixed character grid with no x/y concept at all.
 *
 * All positions are millimeters in the template, converted here to dots
 * at an assumed 203dpi (`DOTS_PER_MM`) — this is the standard thermal
 * head resolution but is NOT verified against this store's actual
 * printer; if a canvas receipt prints at the wrong physical size, this
 * constant is the first thing to check against the real hardware spec
 * (same caveat this session hit with shelf-label sizing).
 *
 * Deliberately does not share code with {@see ArabicTextRasterizer} even
 * though both do "measure + shrink-to-fit + draw" with GD — that class is
 * live, verified, production-critical (the Arabic-print-garbling fix);
 * duplicating ~15 lines here is cheaper than risking a regression there.
 */
class CanvasReceiptRasterizer
{
    private const DOTS_PER_MM = 203 / 25.4;

    // GD/FreeType's imagettftext()/imagettfbbox() treat the "size"
    // argument as points at a fixed 96dpi baseline, regardless of the
    // image's own resolution — every position/width elsewhere in this
    // class is real mm at 203dpi (DOTS_PER_MM), so a raw admin-entered
    // point size passed straight to GD draws glyphs at ~96/203 (~47%) of
    // their true physical size. Confirmed by direct measurement:
    // font_size=18 produced ~2.9mm-tall glyphs instead of the ~6.35mm a
    // true 18pt font is — matches a user report of text looking small on
    // paper no matter how far they raised the font size in the designer,
    // since it was always undersized by the same ratio. Every GD text
    // call in this class must route the font size through fontDots().
    private const GD_POINT_DPI = 96;

    // Keys match ReceiptTemplateElement::FONTS. Only DejaVu Sans renders
    // Arabic correctly (FreeSans came out as tofu boxes when tried for
    // the header/footer fix earlier this session) — drawText() forces
    // this map's 'dejavu_sans' entry whenever the text contains Arabic,
    // regardless of the element's chosen font_family.
    private const FONT_FILES = [
        'dejavu_sans'  => 'resources/fonts/DejaVuSans.ttf',
        'dejavu_serif' => 'resources/fonts/DejaVuSerif.ttf',
        'dejavu_mono'  => 'resources/fonts/DejaVuSansMono.ttf',
    ];

    private const ROW_LINE_HEIGHT_MM = 4.5;

    // Sub-lines (barcode/SKU/discount/batch, printed under an item's own
    // row) sit closer to their item than a full row would — a smaller
    // step, not the same spacing used between two separate items.
    private const SUBLINE_HEIGHT_MM = 3.0;

    // Small margin between the item table's header divider and the first
    // item row — every later row already reads as visually separated (its
    // own row step, plus any sublines above it), but the first row sat
    // flush against the header rule with no breathing room at all.
    private const HEADER_TO_FIRST_ROW_GAP_DOTS = 1.5 * self::DOTS_PER_MM;

    // Sized to fit within 58mm paper's ~48mm printable width (203dpi
    // assumption) so an unconfigured table never overflows the narrowest
    // supported paper — widen via the column editor for 80mm.
    private const DEFAULT_COLUMNS = [
        ['field' => 'name', 'label' => 'Item', 'width_mm' => 20],
        ['field' => 'qty', 'label' => 'Qty', 'width_mm' => 8],
        ['field' => 'unit_price', 'label' => 'Price', 'width_mm' => 10],
        ['field' => 'line_total', 'label' => 'Total', 'width_mm' => 10],
    ];

    private const MIN_HEIGHT_MM = 40.0;

    public function __construct(private RasterToEscPos $raster = new RasterToEscPos()) {}

    public function render(ReceiptTemplate $template, Sale $sale, Company $company, PrinterConfig $config): string
    {
        $widthDots = $config->paperWidth === '58mm' ? 384 : 512;
        $elements = $template->visibleElements()->sortBy('y')->values();

        // Elements are positioned at fixed x/y, chosen once at design time —
        // there's no document flow. Several element types have a REAL
        // height that varies per sale — items_table (more items = more
        // rows), totals (a sale with a discount/rounding adjustment shows
        // more lines than one without), payments (more tenders = more
        // lines) — so anything positioned below one of those cascades
        // down by however much taller it turned out than what the admin
        // designed around (its `height`, if set, is that baseline;
        // otherwise a sane per-type default), PLUS whatever already
        // accumulated from earlier dynamic-height elements above it.
        // Every other element type (text, fields, images, barcode/QR)
        // keeps its exact pre-existing behavior: designed == actual, so
        // it never contributes a shift, only items_table/totals/payments
        // being newly variable-height doesn't retroactively change how
        // anything else on an already-built template positions.
        $shiftForId = [];
        $cumulativeShiftMm = 0.0;
        foreach ($elements as $el) {
            $shiftForId[$el->id] = $cumulativeShiftMm;
            $actual = $this->elementHeightMm($el, $sale, $company);
            $designed = $this->designedHeightMm($el, $actual);
            $cumulativeShiftMm += max(0.0, $actual - $designed);
        }
        $shiftFor = fn (ReceiptTemplateElement $el) => $shiftForId[$el->id] ?? 0.0;

        $heightMm = self::MIN_HEIGHT_MM;
        foreach ($elements as $el) {
            $heightMm = max($heightMm, (float) $el->y + $shiftFor($el) + $this->elementHeightMm($el, $sale, $company));
        }
        $heightDots = max(1, (int) ceil($heightMm * self::DOTS_PER_MM) + (int) round(6 * self::DOTS_PER_MM));

        $img = @imagecreatetruecolor($widthDots, $heightDots);
        if ($img === false) {
            return '';
        }

        try {
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefilledrectangle($img, 0, 0, $widthDots, $heightDots, $white);

            foreach ($elements as $el) {
                $this->drawElement($img, $black, $el, $sale, $company, $widthDots, $shiftFor($el));
            }

            return $this->raster->encodeImage($img, $widthDots);
        } catch (\Throwable $e) {
            return '';
        } finally {
            imagedestroy($img);
        }
    }

    /**
     * The height this element was "designed around" — an explicit
     * `height` always wins; otherwise a per-type baseline for the three
     * sale-data-driven block types (a plain single-tender sale with no
     * discount is the common case each baseline assumes); a `width`-bound
     * text/field element's baseline is ONE line — so wrapping to a second
     * (a long sale number, a store name that turned out longer than
     * expected) cascades a shift below it exactly like the others,
     * instead of silently overlapping whatever comes next; anything else
     * (images, barcode, QR, logo — fixed regardless of content) keeps
     * `$actual` so it never contributes a shift.
     */
    private function designedHeightMm(ReceiptTemplateElement $el, float $actual): float
    {
        if ($el->height !== null) {
            return (float) $el->height;
        }

        if ($el->width && ($el->type === 'text' || $this->isFieldType($el))) {
            $boldAdjustedFontSize = $el->is_bold ? (int) round($el->font_size * 1.15) : $el->font_size;

            return ($this->fontDots($boldAdjustedFontSize) + 4) / self::DOTS_PER_MM;
        }

        return match ($el->type) {
            'items_table' => 2 * self::ROW_LINE_HEIGHT_MM, // header + one item row
            'totals'      => 2 * $this->totalsRowStepDots($el->font_size) / self::DOTS_PER_MM, // Subtotal + Grand Total
            'payments'    => $this->totalsRowStepDots($el->font_size) / self::DOTS_PER_MM,      // one tender
            default       => $actual,
        };
    }

    /**
     * Row-to-row step for {@see drawTotals()}/{@see drawPayments()}, sized to
     * the element's own font instead of the flat {@see ROW_LINE_HEIGHT_MM}
     * (tuned for the items table's ~10pt default) — using that flat mm
     * constant for a 6-row totals block made every row look double-spaced
     * regardless of font size actually chosen.
     */
    private function rowStepDots(int $fontSize): int
    {
        return $this->fontDots($fontSize) + 8;
    }

    /** Same idea as {@see rowStepDots()} but with a bit more breathing room — the item table's own rows read fine at the tighter step, but a 3-6 row totals/payments block looked cramped at the same spacing. */
    private function totalsRowStepDots(int $fontSize): int
    {
        return $this->rowStepDots($fontSize) + 6;
    }

    /** True point size -> GD's own 96dpi-baseline "size" units, scaled up to match this canvas's real {@see DOTS_PER_MM} density — see the class-level comment on {@see GD_POINT_DPI}. Every GD text call in this class must pass its font size through here first. */
    private function fontDots(int $pointSize): int
    {
        return (int) round($pointSize * (self::DOTS_PER_MM * 25.4) / self::GD_POINT_DPI);
    }

    /** Real pixel width of one line of text at this font/size/weight — used to align a row within an element's `width`, unlike {@see wrapParagraph()}'s average-char-width estimate which only needs to be close enough to decide where to break. */
    private function textWidthDots(string $text, int $fontSize, bool $bold, string $fontFamily): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $fontKey = ArabicTextRasterizer::containsArabic($text) ? 'dejavu_sans' : $fontFamily;
        $fontPath = base_path(self::FONT_FILES[$fontKey] ?? self::FONT_FILES['dejavu_sans']);
        if (! is_file($fontPath)) {
            return 0;
        }

        $size = $this->fontDots($bold ? (int) round($fontSize * 1.15) : $fontSize);
        $box = @imagettfbbox($size, 0, $fontPath, $text);

        return $box ? (int) ($box[2] - $box[0]) : 0;
    }

    /** Draw x for one line given the element's `align` and `width` — left is a no-op (matches the HTML preview: a `width`-less div can't center/right-align against anything, so alignment only has visible effect once a width box exists). */
    private function alignedX(int $x, int $boxWidthDots, string $text, int $fontSize, bool $bold, string $fontFamily, string $align): int
    {
        if ($align === 'left' || $boxWidthDots <= 0) {
            return $x;
        }

        $textWidthDots = $this->textWidthDots($text, $fontSize, $bold, $fontFamily);

        return $align === 'center'
            ? $x + max(0, (int) round(($boxWidthDots - $textWidthDots) / 2))
            : $x + max(0, $boxWidthDots - $textWidthDots);
    }

    /** Rough vertical footprint in mm, used only to size the canvas — the items table and any wrapped text/field are the variable-height elements. */
    private function elementHeightMm(ReceiptTemplateElement $el, Sale $sale, Company $company): float
    {
        // items_table/totals/payments must always report their REAL content
        // height here, never $el->height — that field is only a designed-baseline
        // hint for the shift calculation (see designedHeightMm()); letting it
        // override the actual height masks real overflow and can clip content
        // or (worse) zero out the cascade shift below it.
        if ($el->type === 'items_table') {
            return $this->itemsTableHeightMm($el, $sale);
        }

        if ($el->type === 'totals') {
            $widthDots = $el->width ? (int) round((float) $el->width * self::DOTS_PER_MM) : 0;
            $lines = 0;
            foreach ($this->totalsRows($sale) as [$label, $value]) {
                $rowFontSize = $label === 'Grand Total' ? (int) round($el->font_size * 1.15) : $el->font_size;
                $lines += $widthDots > 0
                    ? $this->wrappedLineCount($label.': '.$value, $widthDots, $rowFontSize, $el->font_family)
                    : 1;
            }

            return $lines * $this->totalsRowStepDots($el->font_size) / self::DOTS_PER_MM;
        }

        if ($el->type === 'payments') {
            $widthDots = $el->width ? (int) round((float) $el->width * self::DOTS_PER_MM) : 0;
            $rowTexts = $sale->payments->map(fn ($p) => ($p->paymentMethod?->name ?? $p->method_code).': '.format_money($p->amount))->all();
            if ((float) $sale->change_returned > 0) {
                $rowTexts[] = 'Change: '.format_money($sale->change_returned);
            }
            $lines = 0;
            foreach ($rowTexts as $text) {
                $lines += $widthDots > 0 ? $this->wrappedLineCount($text, $widthDots, $el->font_size, $el->font_family) : 1;
            }

            return max(1, $lines) * $this->totalsRowStepDots($el->font_size) / self::DOTS_PER_MM;
        }

        if ($el->height !== null) {
            return (float) $el->height;
        }

        if ($el->width && ($el->type === 'text' || $this->isFieldType($el))) {
            $text = $el->type === 'text' ? (string) ($el->config['text'] ?? '') : $this->fieldValue($el->type, $sale, $company);
            $maxWidthDots = (int) round((float) $el->width * self::DOTS_PER_MM);
            $boldAdjustedFontSize = $el->is_bold ? (int) round($el->font_size * 1.15) : $el->font_size;
            $size = $this->fontDots($boldAdjustedFontSize);
            $lines = $this->wrappedLineCount($text, $maxWidthDots, $boldAdjustedFontSize, $el->font_family);

            return $lines * ($size + 4) / self::DOTS_PER_MM;
        }

        return self::ROW_LINE_HEIGHT_MM;
    }

    /** Row count only, for {@see elementHeightMm()} — reuses {@see totalsRows()} so this can never drift from what {@see drawTotals()} actually draws. */
    private function totalsRowCount(Sale $sale): int
    {
        return count($this->totalsRows($sale));
    }

    /** Real printed height of one {@see items_table} element for this specific sale — header row + one (possibly multi-line, once a cell wraps) row per item + whatever sublines are on (each at the {@see sublineGapMm()} step). */
    private function itemsTableHeightMm(ReceiptTemplateElement $el, Sale $sale): float
    {
        $columns = $el->config['columns'] ?? self::DEFAULT_COLUMNS;
        $fontSize = max(8, $el->font_size);
        $fontFamily = $el->font_family;
        $tableWidthDots = $this->tableWidthDots($columns);
        $cellPaddingDots = (int) round(0.6 * self::DOTS_PER_MM);
        $sublineGapMm = $this->sublineGapMm($el);
        // Font-proportional, not the flat ROW_LINE_HEIGHT_MM constant — a
        // fixed mm row height only "worked" while every font size rendered
        // at roughly the same (wrong, undersized) physical size regardless
        // of what was configured; now that fontDots() renders text at its
        // true size, a large font needs a taller row or it overlaps the
        // one below it.
        $rowStepMm = $this->rowStepDots($fontSize) / self::DOTS_PER_MM;

        $height = $rowStepMm + (self::HEADER_TO_FIRST_ROW_GAP_DOTS / self::DOTS_PER_MM); // header — labels are short, never wraps
        foreach ($sale->items as $item) {
            $height += $this->itemRowLineCount($item, $columns, $fontSize, $fontFamily, $cellPaddingDots) * $rowStepMm;

            if (! empty($el->config['show_sku_subline']) && ($code = $this->itemSkuOrBarcode($item)) !== '') {
                $height += $this->wrappedLineCount($code, $tableWidthDots, max(7, $fontSize - 2), $fontFamily) * $sublineGapMm;
            }
            if (! empty($el->config['show_barcode_subline']) && ($code = $this->itemBarcodeOnly($item)) !== '') {
                $height += $this->wrappedLineCount($code, $tableWidthDots, max(7, $fontSize - 2), $fontFamily) * $sublineGapMm;
            }
            if (! empty($el->config['show_discount_subline']) && ($label = $this->itemDiscountLabel($item)) !== '') {
                $height += $this->wrappedLineCount($label, $tableWidthDots, max(7, $fontSize - 2), $fontFamily) * $sublineGapMm;
            }
            if (! empty($el->config['show_batch_subline']) && $item->batch) {
                $height += $sublineGapMm;
            }
        }

        return $height;
    }

    /** `config.subline_gap_mm` if the admin set one, else the {@see SUBLINE_HEIGHT_MM} default — the vertical step between an item's sub-lines (SKU/barcode/discount/batch) and between the last sub-line and the next item. */
    private function sublineGapMm(ReceiptTemplateElement $el): float
    {
        return (float) ($el->config['subline_gap_mm'] ?? self::SUBLINE_HEIGHT_MM);
    }

    /**
     * How many lines this item's TALLEST column wraps to, at that column's
     * own width — every column in the row grows to match, since they all
     * share one row of vertical space. `wrappedLineCount()` already treats
     * an empty value as 0 lines; the row itself is never shorter than 1.
     */
    private function itemRowLineCount($item, array $columns, int $fontSize, string $fontFamily, int $cellPaddingDots): int
    {
        $maxLines = 1;
        foreach ($columns as $col) {
            $value = $this->itemColumnValue($item, $col['field'] ?? 'name');
            if ($value === '') {
                continue;
            }
            $widthDots = (int) round(((float) ($col['width_mm'] ?? 20)) * self::DOTS_PER_MM) - (2 * $cellPaddingDots);
            $maxLines = max($maxLines, $this->wrappedLineCount($value, max(10, $widthDots), $fontSize, $fontFamily));
        }

        return $maxLines;
    }

    /** @param array<int, array<string, mixed>> $columns */
    private function tableWidthDots(array $columns): int
    {
        $widthMm = 0.0;
        foreach ($columns as $col) {
            $widthMm += (float) ($col['width_mm'] ?? 20);
        }

        return (int) round($widthMm * self::DOTS_PER_MM);
    }

    private function drawElement(\GdImage $img, int $color, ReceiptTemplateElement $el, Sale $sale, Company $company, int $canvasWidthDots, float $yShiftMm = 0.0): void
    {
        $x = (int) round((float) $el->x * self::DOTS_PER_MM);
        $y = (int) round(((float) $el->y + $yShiftMm) * self::DOTS_PER_MM);

        match (true) {
            $el->type === 'items_table' => $this->drawItemsTable($img, $color, $el, $sale, $x, $y),
            $el->type === 'totals'      => $this->drawTotals($img, $color, $sale, $el, $x, $y),
            $el->type === 'payments'    => $this->drawPayments($img, $color, $sale, $el, $x, $y),
            $el->type === 'barcode'     => $this->drawBarcode($img, $sale->number, $x, $y),
            $el->type === 'qr'          => $this->drawQr($img, $sale->publicReceiptUrl(), $x, $y, $canvasWidthDots),
            $el->type === 'logo'        => $this->drawImageFile($img, $company->logo_path, $x, $y, $el->width),
            $el->type === 'image'       => $this->drawImageFile($img, $el->config['image_path'] ?? null, $x, $y, $el->width),
            $el->type === 'text'        => $this->drawTextOrWrapped($img, $color, (string) ($el->config['text'] ?? ''), $x, $y, $el, $canvasWidthDots),
            $this->isFieldType($el)     => $this->drawTextOrWrapped($img, $color, $this->fieldValue($el->type, $sale, $company), $x, $y, $el, $canvasWidthDots),
            default => null,
        };
    }

    /**
     * A `text`/`field.*` element with an explicit `width` wraps to fit it —
     * anything without one draws as a single line, unchanged from before
     * (every existing template today has no width set on these types, so
     * this is purely additive). Introduced for {@see field.return_policy}:
     * the first element on this path expected to actually hold a long
     * paragraph rather than a short value.
     */
    private function drawTextOrWrapped(\GdImage $img, int $color, string $text, int $x, int $y, ReceiptTemplateElement $el, int $canvasWidthDots): void
    {
        if ($el->width) {
            $maxWidthDots = (int) round((float) $el->width * self::DOTS_PER_MM);
            $this->drawWrappedText($img, $color, $text, $x, $y, $maxWidthDots, $el->font_size, $el->is_bold, $el->font_family, $el->align);

            return;
        }

        $this->drawText($img, $color, $text, $x, $y, $el->font_size, $el->is_bold, $el->font_family);
    }

    private function isFieldType(ReceiptTemplateElement $el): bool
    {
        return $el->isFieldPlaceholder();
    }

    private function fieldValue(string $type, Sale $sale, Company $company): string
    {
        return match ($type) {
            'field.date'          => format_date($sale->sale_datetime ?? $sale->created_at),
            'field.time'          => format_time($sale->sale_datetime ?? $sale->created_at),
            'field.sale_number'   => (string) $sale->number,
            // Label baked into the same field, atomically — a standalone
            // "Customer:" text element would show even on a walk-in sale
            // with no customer, since it can't see whether this field is
            // about to be empty.
            'field.customer_name' => $sale->customer?->name ? 'Customer: '.$sale->customer->name : '',
            'field.cashier_name'  => (string) ($sale->cashier?->name ?? ''),
            'field.store_name'    => (string) ($sale->store?->name ?? ''),
            'field.store_address' => implode(', ', array_filter([
                $sale->store?->address_line1,
                $sale->store?->address_line2,
                implode(', ', array_filter([$sale->store?->city, $sale->store?->state, $sale->store?->postal_code])),
            ])),
            'field.store_phone'   => (string) ($sale->store?->phone ?? ''),
            'field.grand_total'   => format_money($sale->grand_total),
            'field.tax_total'     => format_money($sale->tax_total),
            'field.item_count'    => (string) $sale->items->count(),
            'field.return_policy' => (string) ($company->receipt_return_policy ?? ''),
            'field.receipt_header' => (string) ($company->receipt_header ?? ''),
            'field.receipt_footer' => (string) ($company->receipt_footer ?? ''),
            default               => '',
        };
    }

    /* ── Text drawing (Arabic-safe, shrink-to-fit — same shape as ArabicTextRasterizer, kept separate) ── */

    private function drawText(\GdImage $img, int $color, string $text, int $x, int $y, int $fontSize, bool $bold, string $fontFamily = 'dejavu_sans'): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $isArabic = ArabicTextRasterizer::containsArabic($text);
        // Only DejaVu Sans has Arabic glyphs — force it regardless of the
        // element's chosen font whenever the text needs it.
        $fontKey = $isArabic ? 'dejavu_sans' : $fontFamily;
        $fontPath = base_path(self::FONT_FILES[$fontKey] ?? self::FONT_FILES['dejavu_sans']);
        if (! is_file($fontPath)) {
            return;
        }

        if ($isArabic) {
            try {
                $text = @(new Arabic())->utf8Glyphs($text, 200, false, false);
            } catch (\Throwable $e) {
                // Fall through and draw the un-shaped text rather than nothing.
            }
        }

        // GD font size is bumped ~15% when "bold" is requested — this
        // font file has no separate bold weight, so simulated via size.
        $size = $this->fontDots($bold ? (int) round($fontSize * 1.15) : $fontSize);
        $baselineY = $y + $size + 2;

        @imagettftext($img, $size, 0, $x, $baselineY, $color, $fontPath, $text);
    }

    /**
     * Multi-line version of {@see drawText()} — breaks `$text` to fit
     * `$maxWidthDots`, one line per row. Arabic reuses the exact
     * shape-and-wrap call {@see ArabicTextRasterizer} already relies on
     * (its char-count-per-line estimate, not a pixel measurement, is a
     * known-safe approximation for that path); plain text wraps via
     * `wordwrap()` against a measured average glyph width.
     */
    private function drawWrappedText(\GdImage $img, int $color, string $text, int $x, int $y, int $maxWidthDots, int $fontSize, bool $bold, string $fontFamily = 'dejavu_sans', string $align = 'left'): void
    {
        // Bold text draws ~15% wider (see drawText()) — the wrap decision
        // has to measure at that SAME wider size, or a bold string that
        // "measured as fitting on one line" ends up overflowing once
        // actually drawn bold (confirmed: a bold sale-number field ran
        // off the paper's edge despite an explicit `width` that non-bold
        // text of the same length wrapped correctly within).
        $boldAdjustedFontSize = $bold ? (int) round($fontSize * 1.15) : $fontSize;
        $lines = $this->wrapLines($text, $maxWidthDots, $boldAdjustedFontSize, $fontFamily);
        if ($lines === []) {
            return;
        }

        $fontKey = ArabicTextRasterizer::containsArabic($text) ? 'dejavu_sans' : $fontFamily;
        $fontPath = base_path(self::FONT_FILES[$fontKey] ?? self::FONT_FILES['dejavu_sans']);
        $size = $this->fontDots($boldAdjustedFontSize);
        $lineHeightDots = $size + 4;

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            // Each wrapped line aligns independently within maxWidthDots —
            // matches the HTML preview's `text-align` on a fixed-width div,
            // where a centered paragraph centers every line on its own,
            // not the paragraph as a block.
            $lineX = $this->alignedX($x, $maxWidthDots, $line, $fontSize, $bold, $fontFamily, $align);
            $baselineY = $y + ($i * $lineHeightDots) + $size + 2;
            @imagettftext($img, $size, 0, $lineX, $baselineY, $color, $fontPath, $line);
        }
    }

    /**
     * @return array<int, string>
     *
     * Splits on the caller's own line breaks FIRST, then wraps each
     * resulting line independently — a bilingual paragraph (English
     * block, blank line, Arabic block) needs each side shaped on its
     * own terms. Running the whole blob through Arabic::utf8Glyphs()
     * in one pass (the original approach) reshapes English lines it
     * has no business touching, since {@see ArabicTextRasterizer::containsArabic()}
     * only asks "is there ANY Arabic in here," not "which lines."
     */
    private function wrapLines(string $text, int $maxWidthDots, int $fontSize, string $fontFamily): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $lines[] = '';

                continue;
            }
            array_push($lines, ...$this->wrapParagraph($paragraph, $maxWidthDots, $fontSize, $fontFamily));
        }

        return $lines;
    }

    /** @return array<int, string> */
    private function wrapParagraph(string $text, int $maxWidthDots, int $fontSize, string $fontFamily): array
    {
        $isArabic = ArabicTextRasterizer::containsArabic($text);
        $fontKey = $isArabic ? 'dejavu_sans' : $fontFamily;
        $fontPath = base_path(self::FONT_FILES[$fontKey] ?? self::FONT_FILES['dejavu_sans']);
        if (! is_file($fontPath)) {
            return [$text];
        }

        $sizeDots = $this->fontDots($fontSize);

        if ($isArabic) {
            try {
                $maxCharsPerLine = max(10, intdiv($maxWidthDots, (int) round($sizeDots * 0.85)));
                $shaped = @(new Arabic())->utf8Glyphs($text, $maxCharsPerLine, false, false);

                return explode("\n", $shaped);
            } catch (\Throwable $e) {
                return [$text];
            }
        }

        return $this->greedyWrap($text, $maxWidthDots, $sizeDots, $fontPath);
    }

    /**
     * True greedy word-wrap: measures each candidate line's REAL rendered
     * width via imagettfbbox and only breaks once it would exceed
     * maxWidthDots — accurate regardless of the string's character mix,
     * unlike an average-char-width estimate (tried first: using a fixed
     * 'M' proxy badly under-fit a divider's narrow hyphens; sampling the
     * text's own leading characters instead still skewed wrong whenever
     * that sample wasn't representative of the rest of the string, e.g.
     * a narrower-than-average opening clause in a long sentence). A
     * single token wider than the whole line on its own (an unbroken
     * divider rule, no spaces at all) falls back to a character-level
     * split instead of overflowing past the box.
     *
     * @return array<int, string>
     */
    private function greedyWrap(string $text, int $maxWidthDots, int $sizeDots, string $fontPath): array
    {
        $widthOf = function (string $s) use ($sizeDots, $fontPath): int {
            $box = @imagettfbbox($sizeDots, 0, $fontPath, $s);

            return $box ? $box[2] - $box[0] : 0;
        };

        $lines = [];
        $current = '';
        foreach (preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            if ($widthOf($candidate) <= $maxWidthDots) {
                $current = $candidate;

                continue;
            }
            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }
            if ($widthOf($word) > $maxWidthDots) {
                $piece = '';
                foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                    if ($piece !== '' && $widthOf($piece.$ch) > $maxWidthDots) {
                        $lines[] = $piece;
                        $piece = '';
                    }
                    $piece .= $ch;
                }
                $current = $piece;
            } else {
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }

    /** Line count only — used by {@see elementHeightMm()} to reserve enough vertical room without actually drawing. */
    private function wrappedLineCount(string $text, int $maxWidthDots, int $fontSize, string $fontFamily): int
    {
        return max(1, count($this->wrapLines($text, $maxWidthDots, $fontSize, $fontFamily)));
    }

    /* ── Items table (configurable columns + optional sub-lines) ── */

    private function drawItemsTable(\GdImage $img, int $color, ReceiptTemplateElement $el, Sale $sale, int $x, int $y): void
    {
        $columns = $el->config['columns'] ?? self::DEFAULT_COLUMNS;
        $fontSize = max(8, $el->font_size);
        $fontFamily = $el->font_family;
        $rowHeightDots = $this->rowStepDots($fontSize);
        $subRowHeightDots = (int) round($this->sublineGapMm($el) * self::DOTS_PER_MM);
        $showBorders = ! empty($el->config['show_borders']);
        $cellPaddingDots = (int) round(0.6 * self::DOTS_PER_MM);
        $showCurrency = $el->config['show_currency'] ?? true;

        $colX = [];
        $colWidthDots = [];
        $cursor = $x;
        foreach ($columns as $col) {
            $colX[] = $cursor;
            $w = (int) round(((float) ($col['width_mm'] ?? 20)) * self::DOTS_PER_MM);
            $colWidthDots[] = $w;
            $cursor += $w;
        }
        $tableRight = $cursor;
        $tableWidthDots = $tableRight - $x;
        $tableTop = $y;
        $hLines = []; // y (dots) of each horizontal divider — header, then one per item block

        $rowY = $y;
        foreach ($columns as $i => $col) {
            $this->drawText($img, $color, (string) ($col['label'] ?? ''), $colX[$i] + $cellPaddingDots, $rowY, $fontSize, true, $fontFamily);
        }
        $rowY += $rowHeightDots;
        $hLines[] = $rowY;
        // A small margin between the header divider and the first item's
        // own text — without it the first row sits flush against the
        // rule, while every later row already reads as separated (its own
        // row step plus whatever sublines came before it).
        $rowY += (int) round(self::HEADER_TO_FIRST_ROW_GAP_DOTS);

        foreach ($sale->items as $item) {
            // Every column wraps to its OWN width, but they all share one
            // row of vertical space — draw them at their own line counts,
            // then advance by whichever column ended up tallest, so a long
            // item name can't spill down over the qty/price/total cells to
            // its right (or the row below).
            foreach ($columns as $i => $col) {
                $value = $this->itemColumnValue($item, $col['field'] ?? 'name', $showCurrency);
                $maxWidthDots = max(10, $colWidthDots[$i] - (2 * $cellPaddingDots));
                $this->drawWrappedText($img, $color, $value, $colX[$i] + $cellPaddingDots, $rowY, $maxWidthDots, $fontSize, false, $fontFamily);
            }
            $rowLines = $this->itemRowLineCount($item, $columns, $fontSize, $fontFamily, $cellPaddingDots);
            $rowY += $rowLines * $rowHeightDots;

            if (! empty($el->config['show_sku_subline'])) {
                $code = $this->itemSkuOrBarcode($item);
                if ($code !== '') {
                    $lines = $this->drawWrappedTextCounted($img, $color, '  '.$code, $x, $rowY, $tableWidthDots, max(7, $fontSize - 2), false, $fontFamily);
                    $rowY += $lines * $subRowHeightDots;
                }
            }
            if (! empty($el->config['show_barcode_subline'])) {
                $code = $this->itemBarcodeOnly($item);
                if ($code !== '') {
                    $lines = $this->drawWrappedTextCounted($img, $color, '  '.$code, $x, $rowY, $tableWidthDots, max(7, $fontSize - 2), false, $fontFamily);
                    $rowY += $lines * $subRowHeightDots;
                }
            }
            if (! empty($el->config['show_discount_subline'])) {
                $label = $this->itemDiscountLabel($item);
                if ($label !== '') {
                    $lines = $this->drawWrappedTextCounted($img, $color, '  '.$label, $x, $rowY, $tableWidthDots, max(7, $fontSize - 2), false, $fontFamily);
                    $rowY += $lines * $subRowHeightDots;
                }
            }
            if (! empty($el->config['show_batch_subline']) && $item->batch) {
                $this->drawText($img, $color, '  Batch '.$item->batch->batch_number, $x, $rowY, max(7, $fontSize - 2), false, $fontFamily);
                $rowY += $subRowHeightDots;
            }

            $hLines[] = $rowY;
        }

        if ($showBorders) {
            $this->drawItemsTableGrid($img, $color, $x, $tableTop, $tableRight, $rowY, $colX, $hLines);
        } elseif (! empty($el->config['show_row_dividers'])) {
            $this->drawItemsTableRowDividers($img, $color, $x, $tableRight, $hLines);
        }
    }

    /** {@see drawWrappedText()}, but also reports how many lines it drew, so the caller can advance its own cursor by the same amount. */
    private function drawWrappedTextCounted(\GdImage $img, int $color, string $text, int $x, int $y, int $maxWidthDots, int $fontSize, bool $bold, string $fontFamily): int
    {
        $lines = $this->wrapLines($text, $maxWidthDots, $fontSize, $fontFamily);
        $this->drawWrappedText($img, $color, $text, $x, $y, $maxWidthDots, $fontSize, $bold, $fontFamily);

        return max(1, count($lines));
    }

    /** Outer box + one vertical line per internal column boundary + one horizontal line per row (header and each item block). 2px thick — a single GD pixel is too faint to read on a real 203dpi thermal head. */
    private function drawItemsTableGrid(\GdImage $img, int $color, int $left, int $top, int $right, int $bottom, array $colX, array $hLines): void
    {
        imagesetthickness($img, 2);

        imagerectangle($img, $left, $top, $right, $bottom, $color);
        for ($i = 1; $i < count($colX); $i++) {
            imageline($img, $colX[$i], $top, $colX[$i], $bottom, $color);
        }
        foreach ($hLines as $lineY) {
            imageline($img, $left, $lineY, $right, $lineY, $color);
        }

        imagesetthickness($img, 1);
    }

    /** Just the horizontal rule between each row (header/item boundary) — no outer box, no column lines. The lighter-weight counterpart to {@see drawItemsTableGrid()}'s full grid, for a template that wants row separators without a boxed-in look. */
    private function drawItemsTableRowDividers(\GdImage $img, int $color, int $left, int $right, array $hLines): void
    {
        imagesetthickness($img, 2);
        foreach ($hLines as $lineY) {
            imageline($img, $left, $lineY, $right, $lineY, $color);
        }
        imagesetthickness($img, 1);
    }

    /** SKU preferred, barcode as fallback — "sku or barcode" as one sub-line, same snapshot-then-live-record fallback pattern used everywhere else on the receipt. */
    private function itemSkuOrBarcode($item): string
    {
        $sku = $item->sku_snapshot ?: $item->product?->sku;
        if ($sku) {
            return 'SKU: '.$sku;
        }

        return (string) ($item->barcode_snapshot ?: $item->product?->barcode ?: '');
    }

    /** Barcode always, regardless of SKU — the counterpart toggle to {@see itemSkuOrBarcode()}. Printed bare (no "Barcode:" label) — the digits under the item name are unambiguous on their own. */
    private function itemBarcodeOnly($item): string
    {
        return (string) ($item->barcode_snapshot ?: $item->product?->barcode ?: '');
    }

    private function itemColumnValue($item, string $field, bool $showCurrency = true): string
    {
        return match ($field) {
            'name'       => (string) ($item->product_name_snapshot ?: $item->product?->name),
            'sku'        => (string) ($item->sku_snapshot ?: $item->product?->sku ?: ''),
            'hsn'        => (string) ($item->hsn_snapshot ?: $item->product?->hsn_code ?: ''),
            'qty'        => rtrim(rtrim((string) $item->quantity, '0'), '.') ?: '0',
            'unit_price' => $showCurrency ? format_money($item->unit_price) : $this->plainMoney($item->unit_price),
            'line_total' => $showCurrency ? format_money($item->line_total) : $this->plainMoney($item->line_total),
            default      => '',
        };
    }

    /** Same numeric formatting {@see format_money()} uses, minus the currency symbol — for `items_table.config.show_currency = false`. */
    private function plainMoney(int|float|string|null $amount): string
    {
        $c = app_currency();

        return number_format((float) ($amount ?? 0), $c['decimals'], $c['decimal_separator'] ?: '.', $c['thousands_separator']);
    }

    private function itemDiscountLabel($item): string
    {
        if ((float) ($item->discount_amount ?? 0) > 0) {
            return 'Discount: -'.format_money($item->discount_amount);
        }
        if ((float) ($item->discount_percent ?? 0) > 0) {
            return 'Discount: -'.rtrim(rtrim((string) $item->discount_percent, '0'), '.').'%';
        }

        return '';
    }

    /* ── Totals / payments ── */

    /**
     * Same conditional breakdown the legacy Company-field-driven receipt
     * always showed — Subtotal, then only the lines that actually apply
     * to this sale (a zero discount/rounding/extra-charges sale never
     * shows those rows), then Grand Total.
     */
    private function drawTotals(\GdImage $img, int $color, Sale $sale, ReceiptTemplateElement $el, int $x, int $y): void
    {
        $widthDots = $el->width ? (int) round((float) $el->width * self::DOTS_PER_MM) : 0;
        $stepDots = $this->totalsRowStepDots($el->font_size);
        $rowY = $y;
        foreach ($this->totalsRows($sale) as [$label, $value]) {
            $line = $label.': '.$value;
            $bold = $label === 'Grand Total';
            $rowY += $this->drawAlignedRow($img, $color, $line, $x, $rowY, $widthDots, $stepDots, $el->font_size, $bold, $el->font_family, $el->align);
        }
    }

    /**
     * Draws one "Label: Value" row, wrapping it within `$widthDots` when
     * set — an admin-resized narrow totals/payments box wraps in the HTML
     * preview exactly like any other width-bound div; this keeps the
     * thermal print in step instead of always drawing one unbroken line
     * that can run past the box (and, if the box was made small enough,
     * past the paper's own edge). Returns how far the cursor advanced, so
     * the caller can stack the next row after however many lines this one
     * took.
     */
    private function drawAlignedRow(\GdImage $img, int $color, string $line, int $x, int $y, int $widthDots, int $stepDots, int $fontSize, bool $bold, string $fontFamily, string $align): int
    {
        if ($widthDots <= 0) {
            $this->drawText($img, $color, $line, $x, $y, $fontSize, $bold, $fontFamily);

            return $stepDots;
        }

        $lines = $this->wrapLines($line, $widthDots, $bold ? (int) round($fontSize * 1.15) : $fontSize, $fontFamily);
        foreach ($lines as $i => $wrapped) {
            $lineX = $this->alignedX($x, $widthDots, $wrapped, $fontSize, $bold, $fontFamily, $align);
            $this->drawText($img, $color, $wrapped, $lineX, $y + $i * $stepDots, $fontSize, $bold, $fontFamily);
        }

        return max(1, count($lines)) * $stepDots;
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function totalsRows(Sale $sale): array
    {
        // Sale::subtotal is stored POST-discount, PRE-tax (subtotal + tax_total
        // == grand_total). Displaying it raw as "Subtotal" while also showing
        // a Discount line below double-counts the discount visually — the
        // legacy EscPosFormatter reconstructs the pre-discount gross figure
        // for this exact reason; match that here.
        $grossSubtotal = bcadd(bcadd((string) $sale->subtotal, (string) $sale->tax_total, 4), (string) $sale->discount_total, 4);
        $rows = [['Subtotal', format_money($grossSubtotal)]];

        if ((float) ($sale->discount_total ?? 0) > 0) {
            $rows[] = ['Discount', '-'.format_money($sale->discount_total)];
        }
        // Always shown, even at zero — a customer expects a tax line on
        // every receipt regardless of whether this particular sale had
        // any (unlike discount/charges/rounding, which only apply
        // situationally and would just be visual noise at zero).
        $rows[] = ['Tax (incl.)', format_money($sale->tax_total)];
        if ((float) ($sale->additional_charges_total ?? 0) > 0) {
            $rows[] = ['Additional charges', format_money($sale->additional_charges_total)];
        }
        if ((float) ($sale->rounding_adjustment ?? 0) != 0) {
            $rows[] = ['Rounding', format_money($sale->rounding_adjustment)];
        }
        $rows[] = ['Grand Total', format_money($sale->grand_total)];

        return $rows;
    }

    private function drawPayments(\GdImage $img, int $color, Sale $sale, ReceiptTemplateElement $el, int $x, int $y): void
    {
        $widthDots = $el->width ? (int) round((float) $el->width * self::DOTS_PER_MM) : 0;
        $stepDots = $this->totalsRowStepDots($el->font_size);
        $rowY = $y;
        $drawRow = function (string $line) use ($img, $color, $el, $x, $widthDots, &$rowY, $stepDots) {
            $rowY += $this->drawAlignedRow($img, $color, $line, $x, $rowY, $widthDots, $stepDots, $el->font_size, false, $el->font_family, $el->align);
        };
        foreach ($sale->payments as $payment) {
            $label = $payment->paymentMethod?->name ?? $payment->method_code;
            $drawRow($label.': '.format_money($payment->amount));
        }
        if ((float) $sale->change_returned > 0) {
            $drawRow('Change: '.format_money($sale->change_returned));
        }
    }

    /* ── Images / barcode / QR ── */

    private function drawImageFile(\GdImage $canvas, ?string $path, int $x, int $y, ?string $widthMm): void
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return;
        }

        $raw = @file_get_contents(Storage::disk('public')->path($path));
        $src = $raw !== false ? @imagecreatefromstring($raw) : false;
        if ($src === false) {
            return;
        }

        try {
            $srcW = imagesx($src);
            $srcH = imagesy($src);
            $targetW = $widthMm ? (int) round((float) $widthMm * self::DOTS_PER_MM) : $srcW;
            $targetW = max(1, min($targetW, $srcW * 4));
            $targetH = max(1, (int) round($srcH * ($targetW / $srcW)));

            imagecopyresampled($canvas, $src, $x, $y, 0, 0, $targetW, $targetH, $srcW, $srcH);
        } finally {
            imagedestroy($src);
        }
    }

    private function drawBarcode(\GdImage $canvas, string $value, int $x, int $y): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $type = preg_match('/^\d{12,13}$/', $value)
            ? BarcodeGeneratorPNG::TYPE_EAN_13
            : BarcodeGeneratorPNG::TYPE_CODE_128;

        try {
            $png = (new BarcodeGeneratorPNG())->getBarcode($value, $type, 2, 60);
        } catch (\Throwable $e) {
            return;
        }

        $src = @imagecreatefromstring($png);
        if ($src === false) {
            return;
        }

        try {
            imagecopy($canvas, $src, $x, $y, 0, 0, imagesx($src), imagesy($src));
        } finally {
            imagedestroy($src);
        }
    }

    /**
     * Drawn by hand from the raw QR module matrix rather than through
     * bacon-qr-code's ImageRenderer — this server has no Imagick
     * extension (checked directly), and ImageRenderer's other backends
     * all need one. `Encoder::encode()` alone needs nothing but GD,
     * matching how this app already solved Arabic text without Imagick.
     */
    private function drawQr(\GdImage $canvas, ?string $value, int $x, int $y, int $canvasWidthDots): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        try {
            $qr = Encoder::encode($value, ErrorCorrectionLevel::M());
        } catch (\Throwable $e) {
            return;
        }

        $matrix = $qr->getMatrix();
        $size = $matrix->getWidth();
        $moduleDots = 4; // ~0.5mm/module at 203dpi — legible but compact on receipt paper
        $marginModules = 2;

        // Always horizontally centered on the paper, ignoring the
        // element's own x — the QR's real pixel width depends on the
        // sale URL's length (more modules for a longer string), which
        // the admin can't predict when placing it in the designer, so a
        // fixed x reliably drifts off-center from one receipt to the next.
        $qrWidthDots = ($size + 2 * $marginModules) * $moduleDots;
        $x = (int) round(($canvasWidthDots - $qrWidthDots) / 2);

        $black = imagecolorallocate($canvas, 0, 0, 0);
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($matrix->get($col, $row) === 1) {
                    $px = $x + ($marginModules + $col) * $moduleDots;
                    $py = $y + ($marginModules + $row) * $moduleDots;
                    imagefilledrectangle($canvas, $px, $py, $px + $moduleDots - 1, $py + $moduleDots - 1, $black);
                }
            }
        }
    }
}
