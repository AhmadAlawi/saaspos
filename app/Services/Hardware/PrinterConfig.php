<?php

namespace App\Services\Hardware;

use App\Models\Terminal;

/**
 * Resolved receipt-printer settings for one print (docs/features/
 * hardware.md §4.2). A plain value object the {@see EscPosFormatter}
 * reads: paper width drives the character-per-line count, and the
 * cut/drawer flags decide what trailing commands get appended.
 *
 * Source precedence: a terminal's `default_printer_config.receipt_printer`
 * JSON when present, otherwise a sensible default keyed off the
 * company's configured paper size.
 */
final class PrinterConfig
{
    public function __construct(
        public readonly string $mode = 'browser_print', // webusb | browser_print | network | none
        public readonly string $paperWidth = '80mm',    // 58mm | 80mm | a4
        public readonly int $charWidth = 48,             // characters per line
        public readonly bool $cutPaper = true,
        public readonly bool $openDrawerOnCash = true,
        public readonly int $drawerPin = 2,
    ) {}

    /**
     * Build from a terminal's stored `default_printer_config`. Falls back
     * to {@see self::default()} when the terminal has no receipt-printer
     * config yet (e.g. before the hardware wizard runs).
     */
    public static function fromTerminal(?Terminal $terminal, string $fallbackPaper = '80mm'): self
    {
        $cfg = $terminal?->default_printer_config['receipt_printer'] ?? null;

        if (! is_array($cfg) || $cfg === []) {
            return self::default($fallbackPaper);
        }

        return self::fromArray($cfg, $fallbackPaper);
    }

    /**
     * Build from a raw `receipt_printer` config array.
     *
     * @param array<string, mixed> $cfg
     */
    public static function fromArray(array $cfg, string $fallbackPaper = '80mm'): self
    {
        $paper = self::normalisePaper($cfg['paper_width'] ?? $fallbackPaper);

        return new self(
            mode:             in_array($cfg['mode'] ?? null, ['webusb', 'browser_print', 'network', 'none'], true)
                                ? $cfg['mode']
                                : 'browser_print',
            paperWidth:       $paper,
            charWidth:        isset($cfg['char_width']) && (int) $cfg['char_width'] > 0
                                ? (int) $cfg['char_width']
                                : self::charWidthFor($paper),
            cutPaper:         (bool) ($cfg['cut_paper'] ?? true),
            openDrawerOnCash: (bool) ($cfg['open_drawer_on_cash'] ?? true),
            drawerPin:        ((int) ($cfg['drawer_pin'] ?? 2)) === 5 ? 5 : 2,
        );
    }

    /** Default config keyed off a paper size (no hardware paired). */
    public static function default(string $paper = '80mm'): self
    {
        $paper = self::normalisePaper($paper);

        return new self(
            mode:       'browser_print',
            paperWidth: $paper,
            charWidth:  self::charWidthFor($paper),
        );
    }

    /** Typical characters-per-line for a paper width (Font A). */
    public static function charWidthFor(string $paper): int
    {
        return match ($paper) {
            '58mm'  => 32,
            'a4'    => 64,
            default => 48, // 80mm
        };
    }

    private static function normalisePaper(mixed $paper): string
    {
        $paper = is_string($paper) ? strtolower(trim($paper)) : '80mm';

        return in_array($paper, ['58mm', '80mm', 'a4'], true) ? $paper : '80mm';
    }
}
