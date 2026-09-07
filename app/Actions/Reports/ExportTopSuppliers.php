<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Top Suppliers report as CSV / XLSX / PDF. */
class ExportTopSuppliers
{
    use StreamsReportExport;

    /** Total purchased, Paid, Balance, Outstanding. @return list<int> */
    public function moneyColumns(): array
    {
        return [3, 4, 5, 7];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(Collection $rows): array
    {
        $header = apply_filters('reports.top_suppliers.export.header', [
            'Supplier', 'Code', 'Purchases', 'Total purchased', 'Paid', 'Balance', 'Last purchase', 'Outstanding',
        ]);

        $out = $rows->map(fn ($r) => apply_filters('reports.top_suppliers.export.row', [
            (string) $r['name'],
            (string) ($r['code'] ?? ''),
            (string) $r['purchases_count'],
            (string) $r['total_purchased'],
            (string) $r['paid'],
            (string) $r['balance'],
            (string) $r['last_purchase'],
            (string) $r['outstanding'],
        ], $r))->all();

        return [$header, $out];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function __invoke(Collection $rows, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $out] = $this->build($rows);

        return $this->respondWithReportExport('top-suppliers', __('reports.top_suppliers.title'), $header, $out, $format, $from, $to);
    }
}
