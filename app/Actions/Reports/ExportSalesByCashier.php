<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Sales by Cashier report as CSV / XLSX / PDF. */
class ExportSalesByCashier
{
    use StreamsReportExport;

    /** Revenue, Discounts, Avg basket. @return list<int> */
    public function moneyColumns(): array
    {
        return [3, 4, 5];
    }

    /**
     * Build the export header + rows for this report. Shared by the HTTP
     * export (below) and the headless scheduled-report runner.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(Collection $rows): array
    {
        $header = apply_filters('reports.sales_by_cashier.export.header', [
            'Cashier', 'Sales', 'Items sold', 'Revenue', 'Discounts', 'Avg basket',
        ]);

        $out = $rows->map(fn ($r) => apply_filters('reports.sales_by_cashier.export.row', [
            (string) $r['name'],
            (string) $r['sales_count'],
            (string) $r['items_sold'],
            (string) $r['revenue'],
            (string) $r['discounts'],
            (string) $r['avg_basket'],
        ], $r))->all();

        return [$header, $out];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function __invoke(Collection $rows, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $out] = $this->build($rows);

        return $this->respondWithReportExport('sales-by-cashier', __('reports.sales_by_cashier.title'), $header, $out, $format, $from, $to);
    }
}
