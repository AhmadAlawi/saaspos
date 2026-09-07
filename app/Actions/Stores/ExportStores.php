<?php

namespace App\Actions\Stores;

use App\Events\StoresExported;
use App\Models\Store;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of every store.
 *
 * Extension points:
 *   - filter `stores.export.header`  → adjust the header row
 *   - filter `stores.export.row`     → adjust each data row (row array + Store)
 *   - action `stores.before_export`  → side effects before stream
 *   - action `stores.after_export`   → fires after rows are buffered
 *   - event  StoresExported          → decoupled listeners
 */
class ExportStores
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'stores-'.now()->format('Y-m-d').".{$format}";

        $stores = Store::query()->orderBy('name')->get();

        $header = apply_filters('stores.export.header', [
            'ID',
            'Code',
            'Name',
            'Address',
            'City',
            'State',
            'Zip',
            'Country',
            'Phone',
            'Email',
            'Default',
            'Active',
            'Created at',
            'Updated at',
        ]);

        $rows = $stores->map(function (Store $s) {
            $row = [
                (string) $s->id,
                (string) $s->code,
                (string) $s->name,
                (string) ($s->address_1 ?? ''),
                (string) ($s->city ?? ''),
                (string) ($s->state ?? ''),
                (string) ($s->zip ?? ''),
                (string) ($s->country_code ?? ''),
                (string) ($s->phone ?? ''),
                (string) ($s->email ?? ''),
                $s->is_default ? 'Yes' : 'No',
                $s->is_active ? 'Yes' : 'No',
                optional($s->created_at)->toDateTimeString() ?? '',
                optional($s->updated_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('stores.export.row', $row, $s);
        })->all();

        $count = count($rows);

        do_action('stores.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('stores.after_export', $format, $count);
        event(new StoresExported($format, $count));

        return $response;
    }
}
