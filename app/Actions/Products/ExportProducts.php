<?php

namespace App\Actions\Products;

use App\Events\ProductsExported;
use App\Models\Product;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of every product.
 *
 * Eager-loads category / brand / unit so cells render human names not
 * raw ids. For >10k product catalogs we'll want to switch to
 * `chunkById()` so memory stays flat; the v1 store sizes we target
 * fit comfortably in memory.
 *
 * Extension points:
 *   - filter `products.export.header`  → adjust the header row
 *   - filter `products.export.row`     → adjust each data row (row + Product)
 *   - action `products.before_export`  → side effects before stream
 *   - action `products.after_export`   → fires after rows are buffered
 *   - event  ProductsExported          → decoupled listeners (audit log, stats)
 */
class ExportProducts
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'products-'.now()->format('Y-m-d').".{$format}";

        $products = Product::query()
            ->with([
                'category:id,name',
                'brand:id,name',
                'unit:id,code,name',
                'taxGroup:id,name',
            ])
            ->ordered()
            ->get();

        $header = apply_filters('products.export.header', [
            'ID', 'SKU', 'Barcode', 'Name', 'Category', 'Brand', 'Unit', 'Tax rule',
            'Cost price', 'Selling price', 'MRP',
            'Track stock', 'Sold by weight', 'Track batches', 'Track expiry',
            'HSN', 'Pharmacy schedule', 'Generic name', 'Manufacturer',
            'Active', 'Featured', 'Created at', 'Updated at',
        ]);

        $rows = $products->map(function (Product $p) {
            $row = [
                (string) $p->id,
                (string) $p->sku,
                (string) ($p->barcode ?? ''),
                (string) $p->name,
                optional($p->category)->name ?? '',
                optional($p->brand)->name ?? '',
                $p->unit ? "{$p->unit->name} ({$p->unit->code})" : '',
                optional($p->taxGroup)->name ?? '',
                (string) $p->cost_price,
                (string) $p->selling_price,
                $p->mrp !== null ? (string) $p->mrp : '',
                $p->track_stock     ? 'Yes' : 'No',
                $p->sold_by_weight  ? 'Yes' : 'No',
                $p->track_batches   ? 'Yes' : 'No',
                $p->track_expiry    ? 'Yes' : 'No',
                (string) ($p->hsn_code ?? ''),
                (string) ($p->pharmacy_schedule ?? ''),
                (string) ($p->generic_name ?? ''),
                (string) ($p->manufacturer ?? ''),
                $p->is_active   ? 'Yes' : 'No',
                $p->is_featured ? 'Yes' : 'No',
                optional($p->created_at)->toDateTimeString() ?? '',
                optional($p->updated_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('products.export.row', $row, $p);
        })->all();

        $count = count($rows);

        do_action('products.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('products.after_export', $format, $count);
        event(new ProductsExported($format, $count));

        return $response;
    }
}
