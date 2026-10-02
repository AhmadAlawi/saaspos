<?php

namespace App\Services\Barcodes;

use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Renders a product code as an inline SVG barcode for label printing
 * (docs/features/hardware.md §6.2). Wraps picqer/php-barcode-generator.
 *
 * Auto-picks the symbology: a clean 13-digit numeric value renders as
 * EAN-13 (the retail standard), everything else as Code 128 (which
 * accepts any ASCII SKU). A value that fails its chosen symbology falls
 * back to Code 128 so a label never renders blank.
 */
class BarcodeRenderer
{
    public function __construct(private BarcodeGeneratorSVG $generator = new BarcodeGeneratorSVG()) {}

    /**
     * @param  string  $value   The code to encode (barcode or SKU).
     * @param  float   $height  SVG height in px (scaled by the label CSS).
     * @return string  An <svg> string, or '' when there's nothing to encode.
     */
    public function svg(string $value, float $widthFactor = 2, float $height = 30): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $type = $this->resolveType($value);

        try {
            return $this->stripXmlProlog($this->generator->getBarcode($value, $type, $widthFactor, $height));
        } catch (\Throwable $e) {
            // Fall back to Code 128 if the value didn't satisfy EAN-13.
            if ($type !== BarcodeGeneratorSVG::TYPE_CODE_128) {
                try {
                    return $this->stripXmlProlog($this->generator->getBarcode($value, BarcodeGeneratorSVG::TYPE_CODE_128, $widthFactor, $height));
                } catch (\Throwable $e2) {
                    return '';
                }
            }

            return '';
        }
    }

    /**
     * picqer's SvgRenderer defaults to "standalone" output — an
     * `<?xml ... ?>` processing instruction plus a full `<!DOCTYPE svg
     * PUBLIC ...>` declaration BEFORE the `<svg>` tag (there's no way to
     * ask the library for its "inline" mode through getBarcode()). That's
     * fine for a file saved on its own, but this string gets embedded
     * directly into an HTML label sheet — once per copy of a label, so a
     * multi-copy print run repeats that prolog+DOCTYPE many times over.
     * Browsers don't render a mid-document DOCTYPE as visible content,
     * but it's invalid HTML that some print engines/PDF renderers choke
     * on inconsistently, which is exactly the kind of thing that shows up
     * as "every other label prints blank." Strip down to the bare `<svg
     * ...>…</svg>` element, which is all that's ever wanted here.
     */
    private function stripXmlProlog(string $svg): string
    {
        $pos = strpos($svg, '<svg');
        return $pos === false ? $svg : substr($svg, $pos);
    }

    private function resolveType(string $value): string
    {
        // EAN-13 needs exactly 12 or 13 digits (the 13th is the check digit,
        // which picqer computes/validates).
        if (preg_match('/^\d{12,13}$/', $value)) {
            return BarcodeGeneratorSVG::TYPE_EAN_13;
        }

        return BarcodeGeneratorSVG::TYPE_CODE_128;
    }
}
