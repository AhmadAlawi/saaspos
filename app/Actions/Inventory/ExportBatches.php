<?php

namespace App\Actions\Inventory;

use App\Models\ProductBatch;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of product batches.
 *
 * Honours the same filters as the index page so the export matches
 * what the user is looking at on screen. Each row carries enough
 * context to reconcile against a physical stock count: store name,
 * product name + SKU, batch number, mfg / expiry dates, qty, prices.
 *
 * Extension points:
 *   - filter `batches.export.header`   → adjust the header row
 *   - filter `batches.export.row`      → adjust each data row
 *   - action `batches.before_export`   → side effects before stream
 *   - action `batches.after_export`    → fires after rows are buffered
 *
 * @param array{
 *   store_id?: ?int,
 *   product_id?: ?int,
 *   status?: ?string,   // 'live' | 'expiring_soon' | 'expired'
 *   days?: ?int,        // for status=expiring_soon — defaults to 30
 *   format?: ?string,   // csv | xlsx — default csv
 * } $filters
 */
class ExportBatches
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(array $filters = []): StreamedResponse
    {
        $format   = strtolower((string) ($filters['format'] ?? 'csv'));
        $filename = 'batches-'.now()->format('Y-m-d').".{$format}";

        $query = ProductBatch::query()
            ->with(['store:id,name', 'product:id,sku,name', 'variant:id,sku'])
            ->when(!empty($filters['store_id']),   fn ($q) => $q->where('store_id',   (int) $filters['store_id']))
            ->when(!empty($filters['product_id']), fn ($q) => $q->where('product_id', (int) $filters['product_id']))
            ->orderBy('expiry_date');

        $status = (string) ($filters['status'] ?? '');
        $days   = (int) ($filters['days'] ?? 30);
        if ($status === 'live')          $query->live();
        if ($status === 'expiring_soon') $query->expiringWithin($days);
        if ($status === 'expired')       $query->expired();
        // Mirrors the Archived tab. Without this, exporting from that tab would
        // silently hand back every NON-archived batch instead.
        if ($status === 'archived')      $query->onlyTrashed();

        $batches = $query->get();

        $header = apply_filters('batches.export.header', [
            'Store',
            'Product',
            'SKU',
            'Variant',
            'Batch number',
            'Manufacture date',
            'Expiry date',
            'On hand',
            'Initial qty',
            'Cost price',
            'Selling price',
            'MRP',
            'Status',
        ]);

        $rows = $batches->map(function (ProductBatch $b) {
            $status = $b->isExpired() ? 'expired' : ($b->isExpiringSoon(30) ? 'expiring_soon' : 'live');
            $row = [
                (string) ($b->store?->name ?? ''),
                (string) ($b->product?->name ?? ''),
                (string) ($b->product?->sku ?? ''),
                (string) ($b->variant?->sku ?? ''),
                (string) $b->batch_number,
                optional($b->manufacture_date)->toDateString() ?? '',
                optional($b->expiry_date)->toDateString() ?? '',
                (string) $b->quantity,
                (string) $b->initial_quantity,
                $b->cost_price    !== null ? (string) $b->cost_price    : '',
                $b->selling_price !== null ? (string) $b->selling_price : '',
                $b->mrp           !== null ? (string) $b->mrp           : '',
                $status,
            ];
            return apply_filters('batches.export.row', $row, $b);
        })->all();

        $count = count($rows);

        do_action('batches.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('batches.after_export', $format, $count);

        return $response;
    }
}
