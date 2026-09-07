<?php

namespace App\Actions\Inventory;

use App\Services\Excel\SpreadsheetWriter;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream the Oversold report as CSV / XLSX. Rows are pre-decorated by
 * {@see App\Http\Controllers\Admin\OversoldReportController::decorate}.
 *
 * Hooks:
 *   - filter `inventory.oversold.export.header` — adjust header row
 *   - filter `inventory.oversold.export.row`    — adjust each row (row, sourceArray)
 */
class ExportOversold
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(Collection $rows, string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format) === 'xlsx' ? 'xlsx' : 'csv';
        $filename = 'oversold-'.now()->format('Y-m-d').".{$format}";

        $header = apply_filters('inventory.oversold.export.header', [
            'Store',
            'Product',
            'Variant',
            'SKU',
            'On hand',
            'Oversold by',
            'Last movement',
        ]);

        $data = $rows->map(function (array $r) {
            $row = [
                (string) ($r['store'] ?? ''),
                (string) ($r['product'] ?? ''),
                (string) ($r['variant_label'] ?? ''),
                (string) ($r['sku'] ?? ''),
                $this->fmtNumber($r['on_hand']     ?? 0),
                $this->fmtNumber($r['oversold_by'] ?? 0),
                (string) ($r['last_movement'] ?? ''),
            ];
            return apply_filters('inventory.oversold.export.row', $row, $r);
        })->all();

        return $this->writer->stream($filename, $header, function (callable $write) use ($data) {
            $write($data);
        });
    }

    /** Trim trailing zeros so CSVs read "5" not "5.0000". */
    private function fmtNumber(float|int|string $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.') ?: '0';
    }
}
