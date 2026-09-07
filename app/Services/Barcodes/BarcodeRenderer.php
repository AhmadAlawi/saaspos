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
            return $this->generator->getBarcode($value, $type, $widthFactor, $height);
        } catch (\Throwable $e) {
            // Fall back to Code 128 if the value didn't satisfy EAN-13.
            if ($type !== BarcodeGeneratorSVG::TYPE_CODE_128) {
                try {
                    return $this->generator->getBarcode($value, BarcodeGeneratorSVG::TYPE_CODE_128, $widthFactor, $height);
                } catch (\Throwable $e2) {
                    return '';
                }
            }

            return '';
        }
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
