<?php

namespace App\Services\Translation;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Enumerates every translatable string key from the base-locale
 * `lang/*.php` files (English is the canonical source) and reports how
 * far a given locale is translated. Drives the export/import round-trip
 * (docs/features/multi-language.md §5).
 */
class TranslationCatalog
{
    public function __construct(private string $sourceLocale = 'en') {}

    /**
     * Every key in the source locale, flattened to dot-paths.
     *
     * @return list<array{group: string, key: string, source: string}>
     */
    public function keys(): array
    {
        $dir = lang_path($this->sourceLocale);
        if (! is_dir($dir)) {
            return [];
        }

        $rows = [];
        foreach (glob($dir.'/*.php') as $file) {
            $group = basename($file, '.php');
            $data  = require $file;
            if (! is_array($data)) {
                continue;
            }
            foreach (Arr::dot($data) as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $rows[] = ['group' => $group, 'key' => $key, 'source' => (string) $value];
                }
            }
        }

        return $rows;
    }

    public function totalKeys(): int
    {
        return count($this->keys());
    }

    /** How many DB overrides exist for a locale (capped at the key total). */
    public function translatedCount(string $locale): int
    {
        $overrides = DB::table('translations')->where('locale', $locale)->count();

        return min($overrides, $this->totalKeys());
    }

    /** Translated percentage (0–100) for a locale; 100 for the source itself. */
    public function progress(string $locale): int
    {
        if ($locale === $this->sourceLocale) {
            return 100;
        }
        $total = $this->totalKeys();

        return $total === 0 ? 0 : (int) floor($this->translatedCount($locale) / $total * 100);
    }
}
