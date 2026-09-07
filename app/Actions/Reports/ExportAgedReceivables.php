<?php

namespace App\Actions\Reports;

use App\Services\Excel\SpreadsheetWriter;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream the Aged Receivables report as CSV / XLSX. Rows are decorated
 * by {@see App\Services\Reports\AgedReceivablesQuery} so the export sees
 * the same shape the screen does — one row per customer with bucket
 * sums + total.
 *
 * Hooks:
 *   - filter `reports.aged_receivables.export.header` — adjust header row
 *   - filter `reports.aged_receivables.export.row`    — adjust each row
 */
class ExportAgedReceivables
{
    public function __construct(private SpreadsheetWriter $writer) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(Collection $rows): array
    {
        $header = apply_filters('reports.aged_receivables.export.header', [
            'Customer code',
            'Customer name',
            '0-30 days',
            '31-60 days',
            '61-90 days',
            '90+ days',
            'Total',
            'Open sales',
            'Oldest (days)',
        ]);

        $data = $rows->map(function (array $r) {
            $row = [
                (string) ($r['customer_code'] ?? ''),
                (string) ($r['customer_name'] ?? ''),
                $this->fmtMoney($r['b0_30']),
                $this->fmtMoney($r['b31_60']),
                $this->fmtMoney($r['b61_90']),
                $this->fmtMoney($r['b90_plus']),
                $this->fmtMoney($r['total']),
                (string) (int) ($r['sales_count'] ?? 0),
                (string) (int) ($r['oldest_days'] ?? 0),
            ];
            return apply_filters('reports.aged_receivables.export.row', $row, $r);
        })->all();

        return [$header, $data];
    }

    public function __invoke(Collection $rows, string $format = 'csv'): StreamedResponse
    {
        $format   = strtolower($format) === 'xlsx' ? 'xlsx' : 'csv';
        $filename = 'aged-receivables-'.now()->format('Y-m-d').".{$format}";

        [$header, $data] = $this->build($rows);

        return $this->writer->stream($filename, $header, function (callable $write) use ($data) {
            $write($data);
        });
    }

    /** Money columns in the export carry the raw 4dp value (no symbol).
     *  Trail-zero trim matches the rest of the reports surface. */
    private function fmtMoney(string|float|int|null $v): string
    {
        return rtrim(rtrim(number_format((float) ($v ?? 0), 4, '.', ''), '0'), '.') ?: '0';
    }
}
