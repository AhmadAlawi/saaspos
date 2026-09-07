<?php

namespace App\Actions\TaxComponents;

use App\Events\TaxComponentsExported;
use App\Models\TaxComponent;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all tax components.
 *
 * Extension points:
 *   - filter `tax_components.export.header`  → adjust the header row
 *   - filter `tax_components.export.row`     → adjust each data row
 *   - action `tax_components.before_export`  → side effects before stream
 *   - action `tax_components.after_export`   → fires after rows are buffered
 *   - event  TaxComponentsExported           → decoupled listeners
 */
class ExportTaxComponents
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'tax-components-'.now()->format('Y-m-d').".{$format}";

        $components = TaxComponent::query()->orderBy('name')->get();

        $header = apply_filters('tax_components.export.header', [
            'ID',
            'Code',
            'Name',
            'Rate (%)',
            'Active',
            'Created at',
        ]);

        $rows = $components->map(function (TaxComponent $c) {
            $row = [
                (string) $c->id,
                (string) ($c->code ?? ''),
                (string) $c->name,
                (string) ($c->rate ?? '0'),
                $c->is_active ? 'Yes' : 'No',
                optional($c->created_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('tax_components.export.row', $row, $c);
        })->all();

        $count = count($rows);

        do_action('tax_components.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('tax_components.after_export', $format, $count);
        event(new TaxComponentsExported($format, $count));

        return $response;
    }
}
