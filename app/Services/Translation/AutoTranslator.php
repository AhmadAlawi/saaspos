<?php

namespace App\Services\Translation;

/**
 * Contract for a machine-translation backend used to bulk-populate a
 * new language from the English source strings — see
 * {@see \App\Actions\Languages\AutoTranslateLanguage}. Swappable so a
 * different provider (DeepL, Google Cloud's official paid API, …) can
 * replace {@see GoogleAutoTranslator} later without touching the action
 * that drives the bulk run.
 */
interface AutoTranslator
{
    /** Translate `$text` from `$sourceLocale` into `$targetLocale`. */
    public function translate(string $text, string $targetLocale, string $sourceLocale = 'en'): string;
}
