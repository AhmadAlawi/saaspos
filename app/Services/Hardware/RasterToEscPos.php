<?php

namespace App\Services\Hardware;

/**
 * Converts a logo image to an ESC/POS raster bit-image (docs/features/
 * hardware.md §5.6) using the GS v 0 command. Lets a thermal receipt
 * print the store logo at the top instead of just the name.
 *
 * Best-effort: returns '' when GD is unavailable, the file is missing, or
 * conversion fails — the formatter then falls back to a text header, so a
 * dud logo never breaks a print.
 */
class RasterToEscPos
{
    /**
     * Many thermal printers have a limited receive buffer for a single
     * `GS v 0` command — a tall image in one shot can overrun it and
     * corrupt the entire print into raw garbage instead of the raster
     * image (confirmed on real hardware: a receipt now routinely 2-3x
     * taller than before its font-size rendering was corrected to true
     * physical size, sent as one command, printed as unreadable
     * binary-looking noise). Splitting into bands of this height and
     * sending one `GS v 0` command per band keeps every single command
     * small regardless of the receipt's total length — the printer
     * prints them back-to-back with no visible seam, same as before.
     */
    private const MAX_BAND_HEIGHT_DOTS = 256;

    /**
     * @param string $path     Absolute path to a PNG/JPG/GIF logo.
     * @param int    $maxWidth Max printed width in dots (rounded down to a
     *                         multiple of 8; e.g. 384 for 58mm, 512 for 80mm).
     * @param int    $threshold Luminance cutoff (0-255) for black vs white.
     */
    public function encode(string $path, int $maxWidth = 384, int $threshold = 128): string
    {
        if (! extension_loaded('gd') || ! is_file($path)) {
            return '';
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return '';
        }

        $img = @imagecreatefromstring($raw);
        if ($img === false) {
            return '';
        }

        try {
            return $this->encodeImage($img, $maxWidth, $threshold);
        } catch (\Throwable $e) {
            return '';
        } finally {
            imagedestroy($img);
        }
    }

    /**
     * Same conversion as {@see encode()} but for a GdImage already in
     * memory (e.g. text rendered via imagettftext) rather than a file on
     * disk — used by {@see ArabicTextRasterizer} to raster reshaped
     * Arabic text, since ESC/POS text mode can't render it directly.
     */
    public function encodeImage(\GdImage $img, int $maxWidth = 384, int $threshold = 128): string
    {
        $srcW = imagesx($img);
        $srcH = imagesy($img);
        if ($srcW < 1 || $srcH < 1) {
            return '';
        }

        // Scale down to fit the printer; round width to a multiple of 8 so
        // each row packs into whole bytes. Never upscale.
        $targetW = min($maxWidth, $srcW);
        $targetW = max(8, (int) (floor($targetW / 8) * 8));
        $targetH = max(1, (int) round($srcH * ($targetW / $srcW)));

        $canvas = imagecreatetruecolor($targetW, $targetH);
        // White background so transparent PNGs come out clean (white = not printed).
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, $white);
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

        $bytesPerRow = (int) ($targetW / 8);
        $xL = $bytesPerRow & 0xFF;
        $xH = ($bytesPerRow >> 8) & 0xFF;

        $out = '';
        for ($bandStart = 0; $bandStart < $targetH; $bandStart += self::MAX_BAND_HEIGHT_DOTS) {
            $bandHeight = min(self::MAX_BAND_HEIGHT_DOTS, $targetH - $bandStart);
            $bandData = '';

            for ($y = $bandStart; $y < $bandStart + $bandHeight; $y++) {
                for ($bx = 0; $bx < $bytesPerRow; $bx++) {
                    $byte = 0;
                    for ($bit = 0; $bit < 8; $bit++) {
                        $x = $bx * 8 + $bit;
                        $rgb = imagecolorat($canvas, $x, $y);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        // Rec. 601 luma; dark pixel → printed → bit set (MSB first).
                        $luma = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
                        if ($luma < $threshold) {
                            $byte |= (0x80 >> $bit);
                        }
                    }
                    $bandData .= chr($byte);
                }
            }

            // GS v 0 m xL xH yL yH d1..dk  (m=0 normal density) — one
            // complete command per band, so no single command's data
            // block exceeds MAX_BAND_HEIGHT_DOTS rows regardless of how
            // tall the overall image is.
            $yL = $bandHeight & 0xFF;
            $yH = ($bandHeight >> 8) & 0xFF;
            $out .= "\x1D\x76\x30\x00".chr($xL).chr($xH).chr($yL).chr($yH).$bandData;
        }

        imagedestroy($canvas);

        return $out;
    }
}
