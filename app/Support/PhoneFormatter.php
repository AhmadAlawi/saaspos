<?php

namespace App\Support;

/**
 * Presentation formatter for stored phone numbers.
 *
 * Numbers are stored normalised by {@see PhoneNormalizer} — digits with an
 * optional leading `+` (E.164, e.g. `+916359302924`). For display and export
 * we want a space between the country calling code and the national number
 * (e.g. `+91 6359302924`). That single space does double duty:
 *
 *   - it reads far better in the UI, and
 *   - it forces Excel to treat the value as TEXT even in CSV (a bare run of
 *     digits like `916359302924` is otherwise rendered as `9.16359E+11`).
 *
 * This is a deliberately dependency-free split: ITU country calling codes are
 * a prefix-free set, so a 1- / 2- / 3-digit greedy match is unambiguous. We
 * keep the national part as a single group (no further sub-grouping) so the
 * output is predictable across every region.
 */
class PhoneFormatter
{
    /** The two single-digit calling codes (NANP + Russia/Kazakhstan). */
    private const ONE_DIGIT = ['1', '7'];

    /** Every two-digit calling code. Anything else (with a leading +) is 3-digit. */
    private const TWO_DIGIT = [
        '20', '27', '30', '31', '32', '33', '34', '36', '39', '40', '41', '43',
        '44', '45', '46', '47', '48', '49', '51', '52', '53', '54', '55', '56',
        '57', '58', '60', '61', '62', '63', '64', '65', '66', '81', '82', '84',
        '86', '90', '91', '92', '93', '94', '95', '98',
    ];

    /**
     * Format a stored phone for display / export. Returns an empty string for
     * null/blank input. Numbers without a leading `+` (country unknown) are
     * returned unchanged — we can't reliably split a calling code off them.
     */
    public static function pretty(?string $stored): string
    {
        $value = trim((string) ($stored ?? ''));
        if ($value === '') {
            return '';
        }

        // Public demo: never render real-looking contact numbers. Every
        // phone shown in the admin goes through here, so masking once
        // covers the customer / supplier lists and detail pages.
        if (pos_is_demo()) {
            return demo_mask_phone($value);
        }

        if (! str_starts_with($value, '+')) {
            return $value;
        }

        $digits = substr($value, 1);
        if (! ctype_digit($digits)) {
            return $value;
        }

        $ccLen = self::callingCodeLength($digits);
        $cc    = substr($digits, 0, $ccLen);
        $rest  = substr($digits, $ccLen);

        return $rest === '' ? "+{$cc}" : "+{$cc} {$rest}";
    }

    /** Greedy 1 → 2 → 3 digit match against the prefix-free calling-code set. */
    private static function callingCodeLength(string $digits): int
    {
        if (in_array(substr($digits, 0, 1), self::ONE_DIGIT, true)) {
            return 1;
        }
        if (in_array(substr($digits, 0, 2), self::TWO_DIGIT, true)) {
            return 2;
        }

        return min(3, strlen($digits));
    }
}
