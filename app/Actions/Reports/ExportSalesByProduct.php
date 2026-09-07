<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Sales by Product report as CSV / XLSX / PDF. */
class ExportSalesByProduct
{
    use StreamsReportExport;

    /** Revenue, Cost, Profit. @return list<int> */
    public function moneyColumns(): array
    {
        return [3, 4, 5];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(Collection $rows): array
    {
        $header = apply_filters('reports.sales_by_product.export.header', [
            'Product',
            'SKU',
            'Qty Sold',
            'Revenue',
            'Cost',
            'Profit',
            'Margin %',
        ]);

        $data = $rows->map(function (array $r) {
            $row = [
                (string) $r['name'],
                (string) $r['sku'],
                (string) $r['qty_sold'],
                $this->fmt($r['revenue']),
                $this->fmt($r['cost']),
                $this->fmt($r['profit']),
                number_format((float) $r['margin_pct'], 1).'%',
            ];

            return apply_filters('reports.sales_by_product.export.row', $row, $r);
        })->all();

        return [$header, $data];
    }

    public function __invoke(Collection $rows, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $data] = $this->build($rows);

        return $this->respondWithReportExport('sales-by-product', __('reports.sales_by_product.title'), $header, $data, $format, $from, $to);
    }

    private function fmt(string|float|int|null $v): string
    {
        return rtrim(rtrim(number_format((float) ($v ?? 0), 4, '.', ''), '0'), '.') ?: '0';
    }
}
