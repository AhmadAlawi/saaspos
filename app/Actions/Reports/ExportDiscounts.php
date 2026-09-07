<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Discounts report as CSV / XLSX / PDF. */
class ExportDiscounts
{
    use StreamsReportExport;

    /** Amount (the "Value" column mixes % and fixed, so it stays raw). @return list<int> */
    public function moneyColumns(): array
    {
        return [5];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(Collection $rows): array
    {
        $header = apply_filters('reports.discounts.export.header', [
            'Sale', 'Date', 'Cashier', 'Type', 'Value', 'Amount', 'Category', 'Reason', 'Approved by',
        ]);

        $out = $rows->map(fn ($r) => apply_filters('reports.discounts.export.row', [
            (string) $r['number'],
            (string) $r['date'],
            (string) $r['cashier'],
            (string) $r['type'],
            (string) $r['value'],
            (string) $r['amount'],
            (string) ($r['reason_category'] ?? ''),
            (string) ($r['reason'] ?? ''),
            (string) ($r['approver'] ?? ''),
        ], $r))->all();

        return [$header, $out];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function __invoke(Collection $rows, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $out] = $this->build($rows);

        return $this->respondWithReportExport('discounts', __('reports.discounts.title'), $header, $out, $format, $from, $to);
    }
}
