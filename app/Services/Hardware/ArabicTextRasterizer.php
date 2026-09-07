<?php

namespace App\Services\Hardware;

use ArPHP\I18N\Arabic;

/**
 * Renders free-text containing Arabic script (receipt header/footer/return
 * policy) into an ESC/POS raster bit-image via GD, instead of sending it as
 * ESC/POS text.
 *
 * ESC/POS text mode streams raw bytes against the printer's single-byte
 * codepage table (PC437/CP1252 by default) with no bidi or glyph-shaping
 * engine — UTF-8 Arabic bytes sent as text print as mismatched-codepage
 * garbage, and even a correct Arabic codepage (CP864/CP1256) wouldn't join
 * letters or reorder right-to-left on its own. Rasterizing sidesteps both
 * problems: `Arabic::utf8Glyphs()` reshapes the text into joined
 * presentation-form glyphs in visual (already-reordered) order, GD draws
 * that as pixels, and {@see RasterToEscPos} turns the bitmap into the same
 * `GS v 0` command the logo already uses — the printer just prints dots,
 * no codepage involved.
 */
class ArabicTextRasterizer
{
    // mpdf's bundled FreeSans.ttf has no Arabic glyphs (renders as tofu
    // boxes — checked directly with imagettftext before picking this).
    // DejaVu Sans does, with correct joined/RTL rendering; vendored here
    // rather than relying on the server's dejavu-fonts OS package.
    private const FONT_PATH = 'resources/fonts/DejaVuSans.ttf';

    private const FONT_SIZE = 22;

    private const LINE_HEIGHT = 30;

    private const MIN_FONT_SIZE = 12;

    public function __construct(private RasterToEscPos $raster = new RasterToEscPos()) {}

    public static function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    /**
     * @param  string  $text  Raw UTF-8 text (may mix Arabic and Latin).
     * @param  int  $maxWidth  Printed width in dots, matching {@see RasterToEscPos}'s
     *                         paper-width convention (384 for 58mm, 512 for 80mm).
     * @return string ESC/POS raster bytes, or '' if GD/the font/shaping fails —
     *                the caller should fall back to plain ESC/POS text in that case.
     */
    public function encode(string $text, int $maxWidth = 384): string
    {
        $text = trim($text);
        $fontPath = base_path(self::FONT_PATH);
        if ($text === '' || ! extension_loaded('gd') || ! is_file($fontPath)) {
            return '';
        }

        try {
            // Hindu-Arabic (0-9) digits, not Eastern Arabic numerals — matches
            // the Western digits used everywhere else on the receipt (prices,
            // dates, sale number).
            //
            // @-suppressed: ar-php's glyph-joining pass indexes $prevChar/
            // $nextChar without isset() checks and throws an "undefined
            // array key" warning at some line-start Arabic sequences (e.g.
            // a Lam-Alef combination as the first char). Laravel's default
            // error handler promotes warnings to ErrorException, which the
            // catch below would otherwise treat as a real shaping failure
            // and silently fall back to unshaped (garbled-on-print) text —
            // @ keeps this specific known-noisy call from tripping that.
            $maxCharsPerLine = max(10, intdiv($maxWidth, 14));
            $shaped = @(new Arabic())->utf8Glyphs($text, $maxCharsPerLine, false, false);
        } catch (\Throwable $e) {
            return '';
        }

        $lines = explode("\n", $shaped);
        $img = @imagecreatetruecolor($maxWidth, max(1, count($lines)) * self::LINE_HEIGHT);
        if ($img === false) {
            return '';
        }

        try {
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefilledrectangle($img, 0, 0, $maxWidth, imagesy($img), $white);

            foreach ($lines as $i => $line) {
                if (trim($line) === '') {
                    continue;
                }
                $this->drawLine($img, $black, $fontPath, $line, $i, $maxWidth);
            }

            return $this->raster->encodeImage($img, $maxWidth);
        } catch (\Throwable $e) {
            return '';
        } finally {
            imagedestroy($img);
        }
    }

    /**
     * Draws one shaped line centered on its row, shrinking the font size if
     * needed so a long line never overflows the canvas — the char-count
     * wrap above is an estimate, not a pixel measurement.
     */
    private function drawLine(\GdImage $img, int $color, string $fontPath, string $line, int $row, int $maxWidth): void
    {
        $fontSize = self::FONT_SIZE;
        $box = @imagettfbbox($fontSize, 0, $fontPath, $line);
        $textWidth = $box ? ($box[2] - $box[0]) : 0;

        if ($textWidth > $maxWidth - 4 && $textWidth > 0) {
            $fontSize = max(self::MIN_FONT_SIZE, (int) floor($fontSize * ($maxWidth - 4) / $textWidth));
            $box = @imagettfbbox($fontSize, 0, $fontPath, $line);
            $textWidth = $box ? ($box[2] - $box[0]) : $textWidth;
        }

        $x = max(2, (int) (($maxWidth - $textWidth) / 2));
        $y = ($row * self::LINE_HEIGHT) + self::FONT_SIZE + 2;
        @imagettftext($img, $fontSize, 0, $x, $y, $color, $fontPath, $line);
    }
}
