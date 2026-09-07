<?php

namespace App\Support;

/**
 * Light phone normalizer for storage. Strips spaces, dashes, dots, and
 * parens; keeps a leading `+` if present. The full E.164 validation
 * (via giggsey/libphonenumber) is deferred — when that dep lands, this
 * normalizer can be upgraded in place without touching callers.
 */
class PhoneNormalizer
{
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits  = preg_replace('/[^\d]/', '', $trimmed);
        if ($digits === '') {
            return null;
        }

        return $hasPlus ? '+'.$digits : $digits;
    }
}
