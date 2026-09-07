<?php

namespace App\Services\Barcodes;

/**
 * Parses GS1-128 barcodes (docs/features/hardware.md §7.4-7.5).
 *
 * GS1-128 packs several fields into one barcode, each introduced by a
 * 2-4 digit Application Identifier (AI). Fixed-length AIs (GTIN, dates,
 * weights) carry a known number of digits; variable-length AIs (batch,
 * price) run until a FNC1 group separator (ASCII 29) or the end of the
 * string.
 *
 * Supports the AIs a weighing-scale label uses — GTIN (01), net weight
 * (310x), amount payable (390x/392x and the ISO-currency 391x/393x),
 * expiry (17), and batch (10) — plus common date AIs. Scanners that emit
 * the human-readable bracketed form, e.g. `(01)…(3103)…`, are handled too.
 *
 * Plugins can post-process the decoded AI map via the `barcode.gs128.ais`
 * filter.
 */
class Gs128Parser
{
    /** FNC1 group separator that terminates a variable-length field. */
    private const GS = "\x1d";

    /** 2-digit AIs with a fixed data length. */
    private const FIXED_2 = [
        '00' => 18, '01' => 14, '02' => 14,
        '11' => 6, '12' => 6, '13' => 6, '15' => 6, '16' => 6, '17' => 6,
        '20' => 2,
    ];

    /** 2-digit AIs whose data runs until a separator / end of string. */
    private const VARIABLE_2 = ['10', '21', '22', '30', '37'];

    /** 3-digit families whose 4-digit AI carries a fixed 6-digit measure (n = decimals). */
    private const MEASURE_3 = ['310', '311', '312', '313', '314', '315', '316'];

    /** 3-digit families whose 4-digit AI carries a variable amount (n = decimals). */
    private const AMOUNT_3 = ['390', '392'];

    /** Same as AMOUNT_3 but prefixed with a 3-digit ISO currency code. */
    private const AMOUNT_CURRENCY_3 = ['391', '393'];

    public function parse(string $barcode): ?Gs128Result
    {
        $ais = str_contains($barcode, '(')
            ? $this->parseBracketed($barcode)
            : $this->walk($this->stripSymbology($barcode));

        if ($ais === []) {
            return null;
        }

        /** @var array<string, string> $ais */
        $ais = apply_filters('barcode.gs128.ais', $ais, $barcode);

        return $this->toResult($ais);
    }

    /* ── Raw walking ────────────────────────────────────────────── */

    /** @return array<string, string> */
    private function walk(string $s): array
    {
        $ais = [];
        $i = 0;
        $len = strlen($s);

        while ($i < $len) {
            // Skip stray separators between fields.
            if ($s[$i] === self::GS) { $i++; continue; }

            [$ai, $aiLen, $dataLen] = $this->matchAi($s, $i);
            if ($ai === null) {
                break; // unknown AI — stop rather than mis-read the rest.
            }

            $start = $i + $aiLen;

            if ($dataLen !== null) {
                $data = substr($s, $start, $dataLen);
                if (strlen($data) < $dataLen) {
                    break; // truncated fixed field.
                }
                $i = $start + $dataLen;
            } else {
                // Variable: read to the next group separator or end.
                $gsPos = strpos($s, self::GS, $start);
                $end = $gsPos === false ? $len : $gsPos;
                $data = substr($s, $start, $end - $start);
                $i = $end;
            }

            if ($data === '') {
                break;
            }
            $ais[$ai] = $data;
        }

        return $ais;
    }

    /**
     * Identify the AI at position $i.
     *
     * @return array{0:?string,1:int,2:?int} [ai, aiLength, fixedDataLength|null]
     */
    private function matchAi(string $s, int $i): array
    {
        $two   = substr($s, $i, 2);
        $three = substr($s, $i, 3);
        $four  = substr($s, $i, 4);

        if (in_array($three, self::MEASURE_3, true) && strlen($four) === 4) {
            return [$four, 4, 6];
        }
        if (in_array($three, self::AMOUNT_3, true) && strlen($four) === 4) {
            return [$four, 4, null];
        }
        if (in_array($three, self::AMOUNT_CURRENCY_3, true) && strlen($four) === 4) {
            return [$four, 4, null];
        }
        if (isset(self::FIXED_2[$two])) {
            return [$two, 2, self::FIXED_2[$two]];
        }
        if (in_array($two, self::VARIABLE_2, true)) {
            return [$two, 2, null];
        }

        return [null, 0, null];
    }

    /* ── Bracketed (human-readable) form ────────────────────────── */

    /** @return array<string, string> */
    private function parseBracketed(string $s): array
    {
        $ais = [];
        if (preg_match_all('/\((\d{2,4})\)([^(]*)/', $s, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $ai = $pair[1];
                $data = trim($pair[2]);
                if ($data !== '') {
                    $ais[$ai] = $data;
                }
            }
        }

        return $ais;
    }

    /* ── Interpretation ─────────────────────────────────────────── */

    /** @param array<string, string> $ais */
    private function toResult(array $ais): Gs128Result
    {
        $weight = null;
        $price = null;
        $expiry = null;

        foreach ($ais as $ai => $data) {
            // PHP coerces numeric-string array keys to ints, so an AI like
            // '3103' arrives here as int 3103 — cast back before any string
            // comparison / offset access.
            $ai = (string) $ai;
            $data = (string) $data;
            if (str_starts_with($ai, '310') && strlen($ai) === 4) {
                $weight = $this->scaled($data, (int) $ai[3]);
            } elseif (in_array(substr($ai, 0, 3), self::AMOUNT_3, true) && strlen($ai) === 4) {
                $price = $this->scaled($data, (int) $ai[3]);
            } elseif (in_array(substr($ai, 0, 3), self::AMOUNT_CURRENCY_3, true) && strlen($ai) === 4) {
                // First 3 digits are the ISO currency code; amount follows.
                $price = $this->scaled(substr($data, 3), (int) $ai[3]);
            } elseif ($ai === '17') {
                $expiry = $this->date($data);
            }
        }

        return new Gs128Result(
            gtin:   $ais['01'] ?? null,
            weight: $weight,
            price:  $price,
            expiry: $expiry,
            batch:  $ais['10'] ?? null,
            ais:    $ais,
        );
    }

    private function scaled(string $digits, int $decimals): ?float
    {
        if ($digits === '' || ! ctype_digit($digits)) {
            return null;
        }

        return (int) $digits / (10 ** $decimals);
    }

    /** YYMMDD → Y-m-d. A day of 00 means the last day of the month (GS1 rule). */
    private function date(string $yymmdd): ?string
    {
        if (strlen($yymmdd) !== 6 || ! ctype_digit($yymmdd)) {
            return null;
        }

        $year  = 2000 + (int) substr($yymmdd, 0, 2);
        $month = (int) substr($yymmdd, 2, 2);
        $day   = (int) substr($yymmdd, 4, 2);

        if ($month < 1 || $month > 12) {
            return null;
        }
        if ($day === 0) {
            $day = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        }
        if ($day < 1 || $day > 31) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /** Drop a leading FNC1/symbology hint some scanners prepend (e.g. "]C1"). */
    private function stripSymbology(string $s): string
    {
        if (str_starts_with($s, ']C1')) {
            return substr($s, 3);
        }

        return ltrim($s, self::GS);
    }
}
