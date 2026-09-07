<?php

namespace App\Services\Translation;

use Stichoza\GoogleTranslate\GoogleTranslate;

/**
 * Free, no-API-key machine translation via Google Translate's public
 * endpoint (the `stichoza/google-translate-php` package — the "plugin"
 * driving auto-translation). Good enough for a first-pass populate of a
 * new language; an admin can still hand-correct individual strings
 * afterwards via the existing Export → edit → Import round-trip
 * ({@see \App\Actions\Languages\ExportTranslations}), since both write
 * to the same `translations` table.
 */
class GoogleAutoTranslator implements AutoTranslator
{
    /**
     * Laravel's `__()` placeholders (`:name`, `:count`, …) must survive
     * translation byte-for-byte or the later `str_replace` in the
     * translator silently no-ops. Machine translation can rename, drop,
     * or re-case a `:word` token since it reads like normal text — so
     * each one is swapped for a bracket marker translators reliably
     * leave alone, translated, then swapped back.
     */
    public function translate(string $text, string $targetLocale, string $sourceLocale = 'en'): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $text;
        }

        $placeholders = [];
        $protected = preg_replace_callback('/:[a-zA-Z_][a-zA-Z0-9_]*/', function ($m) use (&$placeholders) {
            $token = '[['.count($placeholders).']]';
            $placeholders[$token] = $m[0];
            return $token;
        }, $text);

        // Bounded timeout — the free endpoint occasionally stalls under
        // rate-limiting instead of returning a 429, and Guzzle has no
        // timeout by default. Without this a single bad call hangs the
        // whole job (and its --timeout flag doesn't help without pcntl).
        $translator = new GoogleTranslate($targetLocale, null, ['timeout' => 10, 'connect_timeout' => 5]);
        $translator->setSource($sourceLocale);

        $translated = $translator->translate($protected);
        if (! is_string($translated) || $translated === '') {
            return $text; // translation failed — keep the English source rather than lose the string
        }

        return strtr($translated, $placeholders);
    }
}
