<?php

namespace App\Services\Barcodes;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders a string (typically a URL) as an inline SVG QR code for the
 * printable receipt — the server-side sibling of {@see BarcodeRenderer}.
 *
 * SVG output needs no GD/Imagick, so it's safe on shared hosting, and it
 * prints crisp at any size. The `<?xml …?>` prolog bacon emits is stripped
 * so the SVG embeds cleanly inside the receipt HTML.
 *
 * Used by the {@see \App\View\Components\ReceiptQr} Blade component; the
 * thermal path emits a *native* printer QR instead (see EscPosCommands::qr).
 */
class QrRenderer
{
    /**
     * @param  string  $value  The content to encode (usually a receipt URL).
     * @param  int     $size   SVG viewport size in px (scaled by receipt CSS).
     * @return string  An <svg> string, or '' when there's nothing to encode.
     */
    public function svg(string $value, int $size = 180): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            $writer = new Writer(new ImageRenderer(
                // Margin of 1 module keeps the quiet zone tight on paper.
                new RendererStyle(max(64, $size), 1),
                new SvgImageBackEnd()
            ));

            // Medium correction survives light thermal-print smudging while
            // keeping the code compact enough for a receipt URL.
            $svg = $writer->writeString($value, 'utf-8', ErrorCorrectionLevel::M());

            // Drop the XML prolog so the SVG embeds inline in HTML.
            return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
        } catch (\Throwable $e) {
            return '';
        }
    }
}
