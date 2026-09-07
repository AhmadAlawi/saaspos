<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Sales by Category report as CSV / XLSX / PDF. */
class ExportSalesByCategory
{
    use StreamsReportExport;

    /** Revenue, Cost, Gross profit. @return list<int> */
    public function moneyColumns(): array
    {
        return [2, 3, 4];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(Collection $rows): array
    {
        $header = apply_filters('reports.sales_by_category.export.header', [
            'Category', 'Qty sold', 'Revenue', 'Cost', 'Gross profit', 'Margin %',
        ]);

        $out = $rows->map(fn ($r) => apply_filters('reports.sales_by_category.export.row', [
            (string) $r['name'],
            (string) $r['qty_sold'],
            (string) $r['revenue'],
            (string) $r['cost'],
            (string) $r['profit'],
            (string) $r['margin_pct'],
        ], $r))->all();

        return [$header, $out];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function __invoke(Collection $rows, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $out] = $this->build($rows);

        return $this->respondWithReportExport('sales-by-category', __('reports.sales_by_category.title'), $header, $out, $format, $from, $to);
    }
}
