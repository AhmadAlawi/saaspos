<?php

namespace App\Actions\Languages;

use App\Models\Language;
use App\Models\Translation;
use App\Services\Translation\AutoTranslator;
use App\Services\Translation\TranslationCatalog;
use App\Support\Translation\DatabaseMergeLoader;
use Illuminate\Support\Facades\Log;

/**
 * Bulk-populates a language from the English "dictionary"
 * ({@see TranslationCatalog}) via a machine-translation backend
 * ({@see AutoTranslator}) — the auto-translate counterpart to the
 * manual Export → edit → Import round-trip. Writes to the exact same
 * `translations` table {@see ImportTranslations} does, so the two are
 * fully interchangeable: auto-translate first for full coverage
 * immediately, then hand-correct individual rows later via the
 * existing Excel export/import without losing anything.
 *
 * Only fills keys with NO existing translation for the target locale
 * by default — safe to re-run after new English strings are added
 * (e.g. a new feature shipped) without overwriting a manual correction
 * someone already made. Pass `overwrite: true` to force a full re-run.
 *
 * Runs from {@see \App\Jobs\AutoTranslateLanguageJob} — never called
 * directly from an HTTP request, since translating a full catalog
 * (one network call per string) takes minutes, not milliseconds.
 *
 * @return array{translated: int, skipped: int, failed: int}
 */
class AutoTranslateLanguage
{
    public function __construct(
        private readonly TranslationCatalog $catalog,
        private readonly AutoTranslator $translator,
    ) {}

    public function __invoke(Language $language, bool $overwrite = false): array
    {
        $rows = $this->catalog->keys();

        $existingPairs = $overwrite
            ? []
            : Translation::query()->where('locale', $language->code)
                ->get(['group', 'key'])
                ->map(fn ($t) => $t->group.'::'.$t->key)
                ->flip()
                ->toArray();

        $translated = 0;
        $skipped    = 0;
        $failed     = 0;
        $batch      = [];

        foreach ($rows as $row) {
            if ($row['source'] === '') {
                $skipped++;
                continue;
            }
            if (! $overwrite && isset($existingPairs[$row['group'].'::'.$row['key']])) {
                $skipped++;
                continue;
            }

            try {
                $value = $this->translator->translate($row['source'], $language->code);
            } catch (\Throwable $e) {
                Log::warning('Auto-translate failed for a key — kept untranslated.', [
                    'locale' => $language->code,
                    'group'  => $row['group'],
                    'key'    => $row['key'],
                    'error'  => $e->getMessage(),
                ]);
                $failed++;
                continue;
            }

            if ($value === '' || $value === $row['source']) {
                // Empty or unchanged — the provider didn't actually
                // translate it (rate-limited, unsupported pair, etc.).
                // Leave the key alone rather than write a no-op row.
                $failed++;
                continue;
            }

            $batch[] = [
                'locale'     => $language->code,
                'group'      => $row['group'],
                'key'        => $row['key'],
                'value'      => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $translated++;

            // Flush periodically so a long run's progress survives a
            // crash/timeout partway rather than losing the whole batch.
            if (count($batch) >= 100) {
                $this->flush($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $this->flush($batch);
        }

        DatabaseMergeLoader::bumpVersion();

        do_action('language.auto_translated', $language, $translated);

        return ['translated' => $translated, 'skipped' => $skipped, 'failed' => $failed];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function flush(array $rows): void
    {
        Translation::query()->upsert($rows, ['locale', 'group', 'key'], ['value', 'updated_at']);
    }
}
