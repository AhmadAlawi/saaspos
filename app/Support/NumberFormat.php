<?php

namespace App\Support;

use App\Models\Store;
use DateTimeInterface;

/**
 * Renders number-format templates like `SALE-{store}-{Ym}-{seq:04}`
 * into concrete numbers like `SALE-MAIN-202606-0042`.
 *
 * Used by GenerateSaleNumber + HoldSale to assign customer-facing
 * receipt numbers. The format string is configured per company at
 * /admin/settings/numbering, with sane defaults baked into
 * Company::numberFormat().
 *
 * Supported placeholders:
 *   {store}       — Store::code, uppercased, alphanum-only
 *   {Y} {y}       — 4 / 2-digit year
 *   {m} {d}       — 2-digit month / day
 *   {Ym} {Ymd}    — combined Y+m / Y+m+d
 *   {seq:N}       — zero-padded sequence (N = pad width)
 *   {seq}         — unpadded sequence
 *
 * Note: at most ONE `{seq...}` placeholder per format. It does not have
 * to be at the end, but the generator's sequence-lookup is simplest
 * when it IS at the end — see `prefix()` below.
 */
class NumberFormat
{
    /**
     * Build the full rendered number for the given sequence value.
     */
    public static function render(string $format, Store $store, DateTimeInterface $when, int $seq): string
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($store->code ?: 'STORE')));
        if ($code === '') $code = 'STORE';

        $simple = [
            '{store}' => $code,
            '{Y}'     => $when->format('Y'),
            '{y}'     => $when->format('y'),
            '{m}'     => $when->format('m'),
            '{d}'     => $when->format('d'),
            '{Ym}'    => $when->format('Ym'),
            '{Ymd}'   => $when->format('Ymd'),
            '{seq}'   => (string) $seq,
        ];
        $result = strtr($format, $simple);

        // Zero-padded sequence: {seq:N} — N can be 1–9.
        $result = preg_replace_callback('/\{seq:(\d+)\}/', function ($m) use ($seq) {
            $pad = max(0, min(9, (int) $m[1]));
            return $pad > 0 ? str_pad((string) $seq, $pad, '0', STR_PAD_LEFT) : (string) $seq;
        }, $result);

        return $result;
    }

    /**
     * Render the format with ALL placeholders filled, EXCEPT the seq one.
     * Used by the sequence-lookup query to find the latest number in the
     * current period via `WHERE number LIKE prefix%`.
     *
     * Example: format `SALE-{store}-{Ym}-{seq:04}`, store MAIN, June 2026
     *  → prefix `SALE-MAIN-202606-`
     */
    public static function prefix(string $format, Store $store, DateTimeInterface $when): string
    {
        // First strip the seq placeholder + anything after it.
        $bare = preg_replace('/\{seq[^\}]*\}.*$/', '', $format);
        if ($bare === null) $bare = $format;
        return self::render($bare, $store, $when, 0);
    }

    /**
     * Best-guess extraction of the sequence value from a previously-saved
     * number. Looks for the longest trailing digit run — works when the
     * `{seq}` placeholder is at the end of the format (the common case).
     * Returns 0 when no digits are found, so the next call starts at 1.
     */
    public static function extractSeq(string $number): int
    {
        if (preg_match('/(\d+)\s*$/', $number, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    /**
     * List the placeholders the docs / settings page surface to the
     * admin. Order matters — keep grouped logically.
     *
     * @return array<int, array{token: string, desc: string}>
     */
    public static function placeholders(): array
    {
        return [
            ['token' => '{store}',   'desc' => 'Store code, uppercased (e.g. MAIN)'],
            ['token' => '{Y}',       'desc' => '4-digit year (2026)'],
            ['token' => '{y}',       'desc' => '2-digit year (26)'],
            ['token' => '{m}',       'desc' => '2-digit month (06)'],
            ['token' => '{d}',       'desc' => '2-digit day (09)'],
            ['token' => '{Ym}',      'desc' => 'Year + month (202606)'],
            ['token' => '{Ymd}',     'desc' => 'Year + month + day (20260609)'],
            ['token' => '{seq:04}',  'desc' => 'Sequence, zero-padded to 4 digits (0042). Use :05 / :06 etc.'],
            ['token' => '{seq}',     'desc' => 'Sequence, no padding (42)'],
        ];
    }
}
