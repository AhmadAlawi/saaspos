<?php

namespace App\Actions\UnitCategories;

use App\Models\UnitCategory;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportUnitCategories
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'unit-categories-'.now()->format('Y-m-d').".{$format}";

        $categories = UnitCategory::ordered()->get();

        $header = ['ID', 'Name', 'Slug', 'Sort Order', 'Active', 'Created at'];

        $rows = $categories->map(fn (UnitCategory $r) => [
            (string) $r->id,
            (string) $r->name,
            (string) $r->slug,
            (string) $r->sort_order,
            $r->is_active ? 'Yes' : 'No',
            optional($r->created_at)->toDateTimeString() ?? '',
        ])->all();

        return $this->writer->stream($filename, $header, function (callable $write) use ($rows) {
            $write($rows);
        });
    }
}
