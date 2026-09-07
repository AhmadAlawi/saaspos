<?php

namespace App\Actions\Inventory;

use App\Events\StockLevelsExported;
use App\Models\StockLevel;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of all stock levels.
 *
 * Extension points:
 *   - filter `stock_levels.export.header`  → adjust the header row
 *   - filter `stock_levels.export.row`     → adjust each data row
 *   - action `stock_levels.before_export`  → side effects before stream
 *   - action `stock_levels.after_export`   → fires after rows are buffered
 *   - event  StockLevelsExported           → decoupled listeners
 */
class ExportStockLevels
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'stock-levels-'.now()->format('Y-m-d').".{$format}";

        $levels = StockLevel::query()
            ->with(['product', 'variant', 'store'])
            ->orderBy('store_id')
            ->orderBy('product_id')
            ->get();

        $header = apply_filters('stock_levels.export.header', [
            'ID',
            'Store',
            'Product',
            'SKU',
            'Variant',
            'On Hand',
            'Reserved',
            'Avg Cost (WAC)',
            'Reorder Level',
            'Last Movement',
        ]);

        $rows = $levels->map(function (StockLevel $l) {
            $row = [
                (string) $l->id,
                (string) ($l->store?->name ?? ''),
                (string) ($l->product?->name ?? ''),
                (string) ($l->variant?->sku ?? $l->product?->sku ?? ''),
                (string) ($l->variant?->label ?? ''),
                (string) ($l->quantity ?? '0'),
                (string) ($l->reserved_quantity ?? '0'),
                (string) ($l->weighted_average_cost ?? ''),
                (string) ($l->reorder_level_override ?? ''),
                optional($l->last_movement_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('stock_levels.export.row', $row, $l);
        })->all();

        $count = count($rows);

        do_action('stock_levels.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('stock_levels.after_export', $format, $count);
        event(new StockLevelsExported($format, $count));

        return $response;
    }
}
