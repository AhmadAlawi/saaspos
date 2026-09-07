<?php

namespace App\Actions\Inventory;

use App\Events\StockTransfersExported;
use App\Models\StockTransfer;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all stock transfers.
 *
 * Extension points:
 *   - filter `stock_transfers.export.header`  → adjust the header row
 *   - filter `stock_transfers.export.row`     → adjust each data row
 *   - action `stock_transfers.before_export`  → side effects before stream
 *   - action `stock_transfers.after_export`   → fires after rows are buffered
 *   - event  StockTransfersExported           → decoupled listeners
 */
class ExportStockTransfers
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'stock-transfers-'.now()->format('Y-m-d').".{$format}";

        $transfers = StockTransfer::query()
            ->with(['fromStore', 'toStore', 'creator'])
            ->orderBy('transfer_date', 'desc')
            ->get();

        $header = apply_filters('stock_transfers.export.header', [
            'ID',
            'Number',
            'Transfer Date',
            'Expected Arrival',
            'From Store',
            'To Store',
            'Status',
            'Notes',
            'Created by',
            'Created at',
        ]);

        $rows = $transfers->map(function (StockTransfer $t) {
            $row = [
                (string) $t->id,
                (string) $t->number,
                optional($t->transfer_date)->toDateString() ?? '',
                optional($t->expected_arrival_date)->toDateString() ?? '',
                (string) ($t->fromStore?->name ?? ''),
                (string) ($t->toStore?->name ?? ''),
                (string) $t->status,
                (string) ($t->notes ?? ''),
                (string) ($t->creator?->name ?? ''),
                optional($t->created_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('stock_transfers.export.row', $row, $t);
        })->all();

        $count = count($rows);

        do_action('stock_transfers.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('stock_transfers.after_export', $format, $count);
        event(new StockTransfersExported($format, $count));

        return $response;
    }
}
