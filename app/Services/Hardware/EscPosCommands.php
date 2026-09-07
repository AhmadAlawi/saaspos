<?php

namespace App\Services\Hardware;

/**
 * Raw ESC/POS command bytes (docs/features/hardware.md §5.2).
 *
 * A thermal printer is driven by a byte stream: printable text
 * interleaved with control sequences (ESC = 0x1B, GS = 0x1D). This
 * const class names the handful of commands a receipt needs so the
 * formatter reads like prose instead of a wall of hex.
 *
 * Compatible with the vast majority of ESC/POS printers (Epson, Star,
 * Bixolon, and the generic Chinese clones that copy Epson's command
 * set).
 */
final class EscPosCommands
{
    /** Re-initialise the printer: clears mode, font, alignment. */
    public const INIT = "\x1B\x40";

    /* ── Alignment ──────────────────────────────────────────────── */
    public const ALIGN_LEFT   = "\x1B\x61\x00";
    public const ALIGN_CENTER = "\x1B\x61\x01";
    public const ALIGN_RIGHT  = "\x1B\x61\x02";

    /* ── Emphasis ───────────────────────────────────────────────── */
    public const BOLD_ON  = "\x1B\x45\x01";
    public const BOLD_OFF = "\x1B\x45\x00";

    /* ── Character size (GS ! n) ────────────────────────────────── */
    public const SIZE_NORMAL       = "\x1D\x21\x00"; // 1× width, 1× height
    public const SIZE_DOUBLE_HEIGHT = "\x1D\x21\x01";
    public const SIZE_DOUBLE_WIDTH  = "\x1D\x21\x10";
    public const SIZE_DOUBLE        = "\x1D\x21\x11"; // 2× width + height

    /* ── Feed + cut ─────────────────────────────────────────────── */
    public const LF        = "\n";
    public const FULL_CUT    = "\x1D\x56\x00";
    public const PARTIAL_CUT = "\x1D\x56\x01";

    /**
     * Cash-drawer kick (ESC p m t1 t2). Default pin 2, ~100ms on / 500ms
     * off, which suits almost every RJ-11-wired drawer.
     */
    public const KICK_DRAWER_PIN2 = "\x1B\x70\x00\x32\xFA";
    public const KICK_DRAWER_PIN5 = "\x1B\x70\x01\x32\xFA";

    /**
     * Feed N blank lines (ESC d n). Used to push the cut past the print
     * head before slicing the paper.
     */
    public static function feed(int $lines): string
    {
        $lines = max(0, min(255, $lines));

        return "\x1B\x64".chr($lines);
    }

    /**
     * Build a drawer-kick sequence for the given pin (2 or 5).
     */
    public static function kickDrawer(int $pin = 2): string
    {
        return $pin === 5 ? self::KICK_DRAWER_PIN5 : self::KICK_DRAWER_PIN2;
    }

    /**
     * Native Code 128 barcode (GS k 73) with the human-readable digits
     * printed below it. Uses code set B, which covers the full ASCII a
     * receipt/sale number needs. Over-long values (>255 bytes) are dropped
     * — a sale number is never close to that.
     */
    public static function barcode128(string $data, int $height = 70, int $module = 2): string
    {
        $payload = '{B'.$data;                 // {B selects code set B
        if (strlen($payload) > 255) {
            return '';
        }

        $module = max(2, min(6, $module));

        return "\x1D\x68".chr(max(1, min(255, $height)))  // GS h — bar height
            ."\x1D\x77".chr($module)                       // GS w — module width
            ."\x1D\x48\x02"                                // GS H — HRI text below
            ."\x1D\x6B\x49".chr(strlen($payload)).$payload; // GS k 73 (Code128)
    }

    /**
     * Native 2-D QR code (GS ( k, model 2) — the printer rasterises it, so
     * no image bytes travel over the wire. Encodes a receipt URL a customer
     * scans for the digital receipt. Emits nothing for empty or over-long
     * data (a URL is nowhere near the 7089-byte model-2 ceiling).
     *
     * @param  int     $module  Dot size 1–16 (≈4–5 suits an 80mm receipt).
     * @param  string  $ecc     Error-correction level: L, M, Q, or H.
     */
    public static function qr(string $data, int $module = 5, string $ecc = 'M'): string
    {
        if ($data === '' || strlen($data) > 7089) {
            return '';
        }

        $module = max(1, min(16, $module));
        $eccByte = chr(['L' => 48, 'M' => 49, 'Q' => 50, 'H' => 51][$ecc] ?? 49);

        // Data length + 3 (cn, fn, m) for the store-data function, little-endian.
        $len = strlen($data) + 3;
        $pL  = chr($len & 0xFF);
        $pH  = chr(($len >> 8) & 0xFF);

        return "\x1D\x28\x6B\x04\x00\x31\x41\x32\x00"          // fn 65 — select model 2
            ."\x1D\x28\x6B\x03\x00\x31\x43".chr($module)        // fn 67 — module size
            ."\x1D\x28\x6B\x03\x00\x31\x45".$eccByte            // fn 69 — error correction
            ."\x1D\x28\x6B".$pL.$pH."\x31\x50\x30".$data        // fn 80 — store data
            ."\x1D\x28\x6B\x03\x00\x31\x51\x30";                // fn 81 — print symbol
    }
}
