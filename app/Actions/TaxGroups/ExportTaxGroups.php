<?php

namespace App\Actions\TaxGroups;

use App\Events\TaxGroupsExported;
use App\Models\TaxGroup;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all tax groups.
 *
 * Extension points:
 *   - filter `tax_groups.export.header`  → adjust the header row
 *   - filter `tax_groups.export.row`     → adjust each data row
 *   - action `tax_groups.before_export`  → side effects before stream
 *   - action `tax_groups.after_export`   → fires after rows are buffered
 *   - event  TaxGroupsExported           → decoupled listeners
 */
class ExportTaxGroups
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'tax-groups-'.now()->format('Y-m-d').".{$format}";

        $groups = TaxGroup::query()->with('components')->orderBy('name')->get();

        $header = apply_filters('tax_groups.export.header', [
            'ID',
            'Code',
            'Name',
            'Classification',
            'Rate',
            'Components',
            'Active',
            'Created at',
        ]);

        $rows = $groups->map(function (TaxGroup $g) {
            $rate = $g->components->sum(fn ($c) => (float) $c->rate);
            $row = [
                (string) $g->id,
                (string) ($g->code ?? ''),
                (string) $g->name,
                (string) ($g->classification ?? ''),
                number_format($rate, 2) . '%',
                $g->components->pluck('name')->implode(', '),
                $g->is_active ? 'Yes' : 'No',
                optional($g->created_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('tax_groups.export.row', $row, $g);
        })->all();

        $count = count($rows);

        do_action('tax_groups.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('tax_groups.after_export', $format, $count);
        event(new TaxGroupsExported($format, $count));

        return $response;
    }
}
