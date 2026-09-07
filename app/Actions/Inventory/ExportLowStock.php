<?php

namespace App\Actions\Inventory;

use App\Services\Excel\SpreadsheetWriter;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream the Low-stock report as CSV / XLSX. Rows are pre-decorated by
 * {@see App\Http\Controllers\Admin\LowStockReportController::decorate}
 * so the export sees the same shape the screen does — store, product,
 * variant label, sku, on-hand, threshold, deficit.
 *
 * Hooks:
 *   - filter `inventory.low_stock.export.header` — adjust header row
 *   - filter `inventory.low_stock.export.row`    — adjust each row (row, sourceArray)
 */
class ExportLowStock
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(Collection $rows, string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format) === 'xlsx' ? 'xlsx' : 'csv';
        $filename = 'low-stock-'.now()->format('Y-m-d').".{$format}";

        $header = apply_filters('inventory.low_stock.export.header', [
            'Store',
            'Product',
            'Variant',
            'SKU',
            'On hand',
            'Reorder at',
            'Deficit',
        ]);

        $data = $rows->map(function (array $r) {
            $row = [
                (string) ($r['store'] ?? ''),
                (string) ($r['product'] ?? ''),
                (string) ($r['variant_label'] ?? ''),
                (string) ($r['sku'] ?? ''),
                $this->fmtNumber($r['on_hand']    ?? 0),
                $this->fmtNumber($r['threshold']  ?? 0),
                $this->fmtNumber($r['deficit']    ?? 0),
            ];
            return apply_filters('inventory.low_stock.export.row', $row, $r);
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
