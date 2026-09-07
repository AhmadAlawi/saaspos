<?php

namespace App\Actions\Terminals;

use App\Models\Terminal;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of a store's terminals. Terminals are
 * managed storewise, so the export is scoped to the active store; a null
 * store id yields an empty file rather than every store's terminals.
 *
 * Extension points:
 *   - filter `terminals.export.header` → adjust the header row
 *   - filter `terminals.export.row`    → adjust each data row (row array + Terminal)
 *   - action `terminals.before_export` / `terminals.after_export`
 */
class ExportTerminals
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv', ?int $storeId = null): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'terminals-'.now()->format('Y-m-d').".{$format}";

        $terminals = Terminal::query()
            ->with('store')
            ->when($storeId, fn ($q) => $q->forStore($storeId), fn ($q) => $q->whereRaw('1 = 0'))
            ->ordered()
            ->get();

        $header = apply_filters('terminals.export.header', [
            'ID',
            'Store',
            'Code',
            'Name',
            'Active',
            'Last seen',
            'Created at',
        ]);

        $rows = $terminals->map(function (Terminal $t) {
            $row = [
                (string) $t->id,
                (string) ($t->store?->name ?? ''),
                (string) $t->code,
                (string) $t->name,
                $t->is_active ? 'Yes' : 'No',
                optional($t->last_seen_at)->toDateTimeString() ?? '',
                optional($t->created_at)->toDateTimeString() ?? '',
            ];

            return apply_filters('terminals.export.row', $row, $t);
        })->all();

        $count = count($rows);

        do_action('terminals.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('terminals.after_export', $format, $count);

        return $response;
    }
}
