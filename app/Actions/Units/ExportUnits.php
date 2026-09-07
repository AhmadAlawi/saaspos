<?php

namespace App\Actions\Units;

use App\Events\UnitsExported;
use App\Models\Unit;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of every unit.
 *
 * Extension points:
 *   - filter `units.export.header`  → adjust the header row
 *   - filter `units.export.row`     → adjust each data row (row array + Unit)
 *   - action `units.before_export`  → side effects before stream
 *   - action `units.after_export`   → fires after rows are buffered
 *   - event  UnitsExported          → decoupled listeners
 */
class ExportUnits
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'units-'.now()->format('Y-m-d').".{$format}";

        $units = Unit::query()
            ->with('baseUnit:id,code,name')
            ->ordered()
            ->get();

        $header = apply_filters('units.export.header', [
            'ID',
            'Code',
            'Name',
            'Category',
            'Base unit',
            'Conversion factor',
            'Active',
            'Created at',
            'Updated at',
        ]);

        $rows = $units->map(function (Unit $u) {
            $row = [
                (string) $u->id,
                (string) $u->code,
                (string) $u->name,
                (string) $u->category,
                $u->baseUnit ? "{$u->baseUnit->name} ({$u->baseUnit->code})" : '',
                $u->conversion_factor !== null ? (string) $u->conversion_factor : '',
                $u->is_active ? 'Yes' : 'No',
                optional($u->created_at)->toDateTimeString() ?? '',
                optional($u->updated_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('units.export.row', $row, $u);
        })->all();

        $count = count($rows);

        do_action('units.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('units.after_export', $format, $count);
        event(new UnitsExported($format, $count));

        return $response;
    }
}
