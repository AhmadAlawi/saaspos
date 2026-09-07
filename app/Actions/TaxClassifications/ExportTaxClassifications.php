<?php

namespace App\Actions\TaxClassifications;

use App\Models\TaxClassification;
use App\Services\Excel\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTaxClassifications
{
    public function __construct(private SpreadsheetWriter $writer) {}

    public function __invoke(string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format);
        $filename = 'tax-classifications-'.now()->format('Y-m-d').".{$format}";

        $rows = TaxClassification::ordered()->get();

        $header = ['ID', 'Name', 'Slug', 'Description', 'Sort Order', 'Active', 'Created at'];

        $data = $rows->map(fn (TaxClassification $r) => [
            (string) $r->id,
            (string) $r->name,
            (string) $r->slug,
            (string) ($r->description ?? ''),
            (string) $r->sort_order,
            $r->is_active ? 'Yes' : 'No',
            optional($r->created_at)->toDateTimeString() ?? '',
        ])->all();

        return $this->writer->stream($filename, $header, function (callable $write) use ($data) {
            $write($data);
        });
    }
}
