<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Shifts by Cashier report as CSV / XLSX / PDF. */
class ExportShiftsByCashier
{
    use StreamsReportExport;

    /** Total sales, Cash variance (hours columns stay raw). @return list<int> */
    public function moneyColumns(): array
    {
        return [4, 5];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(Collection $rows): array
    {
        $header = apply_filters('reports.shifts_by_cashier.export.header', [
            'Cashier', 'Shifts', 'Total hours', 'Avg duration (h)', 'Total sales', 'Cash variance',
        ]);

        $out = $rows->map(fn ($r) => apply_filters('reports.shifts_by_cashier.export.row', [
            (string) $r['name'],
            (string) $r['shifts_count'],
            (string) $r['total_hours'],
            (string) $r['avg_duration'],
            (string) $r['total_sales'],
            (string) $r['total_variance'],
        ], $r))->all();

        return [$header, $out];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function __invoke(Collection $rows, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $out] = $this->build($rows);

        return $this->respondWithReportExport('shifts-by-cashier', __('reports.shifts_by_cashier.title'), $header, $out, $format, $from, $to);
    }
}
