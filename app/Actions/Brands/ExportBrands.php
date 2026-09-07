<?php

namespace App\Actions\Brands;

use App\Events\BrandsExported;
use App\Models\Brand;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Build a downloadable spreadsheet of every brand.
 *
 * Extension points:
 *   - filter `brands.export.header`  → adjust the header row
 *   - filter `brands.export.row`     → adjust each data row (row array + Brand)
 *   - action `brands.before_export`  → side effects before stream
 *   - action `brands.after_export`   → fires after rows are buffered
 *   - event  BrandsExported          → decoupled listeners
 */
class ExportBrands
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'brands-'.now()->format('Y-m-d').".{$format}";

        $brands = Brand::query()->ordered()->get();

        $header = apply_filters('brands.export.header', [
            'ID',
            'Name',
            'Description',
            'Active',
            'Created at',
            'Updated at',
        ]);

        $rows = $brands->map(function (Brand $b) {
            $row = [
                (string) $b->id,
                (string) $b->name,
                (string) ($b->description ?? ''),
                $b->is_active ? 'Yes' : 'No',
                optional($b->created_at)->toDateTimeString() ?? '',
                optional($b->updated_at)->toDateTimeString() ?? '',
            ];
            return apply_filters('brands.export.row', $row, $b);
        })->all();

        $count = count($rows);

        do_action('brands.before_export', $format, $count);

        $response = $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });

        do_action('brands.after_export', $format, $count);
        event(new BrandsExported($format, $count));

        return $response;
    }
}
