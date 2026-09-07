<?php

namespace App\Actions\Inventory;

use App\Models\StockTake;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all stock takes.
 *
 * Extension points:
 *   - filter `stock_takes.export.header` → adjust the header row
 *   - filter `stock_takes.export.row`    → adjust each data row
 *   - action `stock_takes.before_export` → side effects before stream
 *   - action `stock_takes.after_export`  → fires after rows are buffered
 */
class ExportStockTakes
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'stock-takes-'.now()->format('Y-m-d').".{$format}";

        $takes = StockTake::query()
            ->with(['store', 'creator', 'poster'])
            ->withCount('items')
            ->orderByDesc('take_date')
            ->orderByDesc('id')
            ->get();

        $header = apply_filters('stock_takes.export.header', [
            'ID',
            'Number',
            'Name',
            'Date',
            'Store',
            'Status',
            'Items',
            'Posted at',
            'Posted by',
            'Created by',
            'Created at',
        ]);

        $rows = $takes->map(function (StockTake $t) {
            $row = [
                (string) $t->id,
                (string) $t->number,
                (string) ($t->name ?? ''),
                optional($t->take_date)->toDateString() ?? '',
                (string) ($t->store?->name ?? ''),
                (string) $t->status,
                (string) ($t->items_count ?? 0),
                optional($t->posted_at)->toDateTimeString() ?? '',
                (string) ($t->poster?->name ?? ''),
                (string) ($t->creator?->name ?? ''),
                optional($t->created_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('stock_takes.export.row', $row, $t);
        })->all();

        $count = count($rows);

        do_action('stock_takes.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('stock_takes.after_export', $format, $count);

        return $response;
    }
}
