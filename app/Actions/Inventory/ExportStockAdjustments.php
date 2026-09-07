<?php

namespace App\Actions\Inventory;

use App\Events\StockAdjustmentsExported;
use App\Models\StockAdjustment;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all stock adjustments.
 *
 * Extension points:
 *   - filter `stock_adjustments.export.header`  → adjust the header row
 *   - filter `stock_adjustments.export.row`     → adjust each data row
 *   - action `stock_adjustments.before_export`  → side effects before stream
 *   - action `stock_adjustments.after_export`   → fires after rows are buffered
 *   - event  StockAdjustmentsExported           → decoupled listeners
 */
class ExportStockAdjustments
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'stock-adjustments-'.now()->format('Y-m-d').".{$format}";

        $adjustments = StockAdjustment::query()
            ->with(['store', 'reasonCode', 'creator'])
            ->orderBy('adjustment_date', 'desc')
            ->get();

        $header = apply_filters('stock_adjustments.export.header', [
            'ID',
            'Number',
            'Date',
            'Store',
            'Reason',
            'Notes',
            'Status',
            'Posted at',
            'Created by',
            'Created at',
        ]);

        $rows = $adjustments->map(function (StockAdjustment $a) {
            $row = [
                (string) $a->id,
                (string) $a->number,
                optional($a->adjustment_date)->toDateString() ?? '',
                (string) ($a->store?->name ?? ''),
                (string) ($a->reasonCode?->name ?? $a->reason ?? ''),
                (string) ($a->notes ?? ''),
                (string) $a->status,
                optional($a->posted_at)->toDateTimeString() ?? '',
                (string) ($a->creator?->name ?? ''),
                optional($a->created_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('stock_adjustments.export.row', $row, $a);
        })->all();

        $count = count($rows);

        do_action('stock_adjustments.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('stock_adjustments.after_export', $format, $count);
        event(new StockAdjustmentsExported($format, $count));

        return $response;
    }
}
