<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Top Customers report as CSV / XLSX / PDF. */
class ExportTopCustomers
{
    use StreamsReportExport;

    /** Total spent, Avg basket, Outstanding. @return list<int> */
    public function moneyColumns(): array
    {
        return [4, 5, 7];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(Collection $rows): array
    {
        $header = apply_filters('reports.top_customers.export.header', [
            'Customer', 'Code', 'Group', 'Visits', 'Total spent', 'Avg basket', 'Last visit', 'Outstanding',
        ]);

        $out = $rows->map(fn ($r) => apply_filters('reports.top_customers.export.row', [
            (string) $r['name'],
            (string) ($r['code'] ?? ''),
            (string) ($r['group_name'] ?? ''),
            (string) $r['visits'],
            (string) $r['total_spent'],
            (string) $r['avg_basket'],
            (string) $r['last_visit'],
            (string) $r['outstanding'],
        ], $r))->all();

        return [$header, $out];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function __invoke(Collection $rows, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $out] = $this->build($rows);

        return $this->respondWithReportExport('top-customers', __('reports.top_customers.title'), $header, $out, $format, $from, $to);
    }
}
