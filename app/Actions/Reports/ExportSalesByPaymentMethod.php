<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Sales by Payment Method report as CSV / XLSX / PDF. */
class ExportSalesByPaymentMethod
{
    use StreamsReportExport;

    /** Total received. @return list<int> */
    public function moneyColumns(): array
    {
        return [3];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(Collection $rows): array
    {
        $header = apply_filters('reports.sales_by_payment_method.export.header', [
            'Payment method', 'Type', 'Sales', 'Total received',
        ]);

        $out = $rows->map(fn ($r) => apply_filters('reports.sales_by_payment_method.export.row', [
            (string) $r['name'],
            (string) $r['type'],
            (string) $r['sales_count'],
            (string) $r['total'],
        ], $r))->all();

        return [$header, $out];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function __invoke(Collection $rows, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $out] = $this->build($rows);

        return $this->respondWithReportExport('sales-by-payment-method', __('reports.sales_by_payment_method.title'), $header, $out, $format, $from, $to);
    }
}
