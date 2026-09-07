<?php

namespace App\Actions\Languages;

use App\Models\Language;
use App\Services\Excel\SpreadsheetWriter;
use App\Services\Translation\TranslationCatalog;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream an `.xlsx` of every translatable key for a language: the
 * English reference plus the language's current translation (blank when
 * untranslated). The owner edits only the `Translation` column and
 * re-uploads — see {@see ImportTranslations}.
 */
class ExportTranslations
{
    public function __construct(
        private SpreadsheetWriter $writer,
        private TranslationCatalog $catalog,
    ) {}

    public function __invoke(Language $language): StreamedResponse
    {
        $filename = "language-{$language->code}-".now()->format('Y-m-d').'.xlsx';

        // Existing overrides for this locale, keyed "group.key" → value.
        $current = DB::table('translations')
            ->where('locale', $language->code)
            ->get(['group', 'key', 'value'])
            ->mapWithKeys(fn ($r) => ["{$r->group}.{$r->key}" => $r->value])
            ->all();

        $header = ['Key', 'Section', 'English', 'Translation'];

        $rows = array_map(function (array $row) use ($current) {
            return [
                $row['key'],
                $row['group'],
                $row['source'],
                $current["{$row['group']}.{$row['key']}"] ?? '',
            ];
        }, $this->catalog->keys());

        return $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });
    }
}
