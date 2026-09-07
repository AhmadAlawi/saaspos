<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Sales Summary daily breakdown as CSV / XLSX / PDF. */
class ExportSalesSummary
{
    use StreamsReportExport;

    /** Subtotal, Discount, Tax, Grand Total. @return list<int> */
    public function moneyColumns(): array
    {
        return [2, 3, 4, 5];
    }

    /**
     * @param  array<string, mixed>  $data  output of {@see \App\Services\Reports\SalesSummaryQuery}
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(array $data): array
    {
        $header = apply_filters('reports.sales_summary.export.header', [
            'Date',
            'Transactions',
            'Subtotal',
            'Discount',
            'Tax',
            'Grand Total',
        ]);

        $rows = collect($data['by_day'])->map(function ($r) {
            $row = [
                (string) $r->day,
                (string) (int) $r->transactions,
                $this->fmt($r->subtotal),
                $this->fmt($r->discount),
                $this->fmt($r->tax),
                $this->fmt($r->grand_total),
            ];

            return apply_filters('reports.sales_summary.export.row', $row, (array) $r);
        })->all();

        // Totals row
        $totals = $data['totals'];
        $rows[] = [
            'TOTAL',
            (string) $data['kpis']['transactions'],
            $this->fmt($totals['subtotal']),
            $this->fmt($totals['discount']),
            $this->fmt($totals['tax']),
            $this->fmt($totals['grand_total']),
        ];

        return [$header, $rows];
    }

    public function __invoke(array $data, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $rows] = $this->build($data);

        return $this->respondWithReportExport('sales-summary', __('reports.sales_summary.title'), $header, $rows, $format, $from, $to);
    }

    private function fmt(string|float|int|null $v): string
    {
        return rtrim(rtrim(number_format((float) ($v ?? 0), 4, '.', ''), '0'), '.') ?: '0';
    }
}
