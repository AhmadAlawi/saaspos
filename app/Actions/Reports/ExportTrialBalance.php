<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Trial Balance as CSV / XLSX / PDF. */
class ExportTrialBalance
{
    use StreamsReportExport;

    /** Debit, Credit. @return list<int> */
    public function moneyColumns(): array
    {
        return [2, 3];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(Collection $rows): array
    {
        $header = apply_filters('reports.trial_balance.export.header', [
            'Code', 'Account', 'Debit', 'Credit',
        ]);

        $out = $rows->map(fn ($r) => apply_filters('reports.trial_balance.export.row', [
            (string) $r['code'],
            (string) $r['name'],
            $this->fmt($r['debit']),
            $this->fmt($r['credit']),
        ], $r))->all();

        return [$header, $out];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    public function __invoke(Collection $rows, string $asOf, string $format = 'csv'): StreamedResponse
    {
        [$header, $out] = $this->build($rows);

        return $this->respondWithReportExport('trial-balance', __('reports.trial_balance.title'), $header, $out, $format, $asOf, $asOf);
    }

    private function fmt(string|float|int|null $v): string
    {
        return rtrim(rtrim(number_format((float) ($v ?? 0), 4, '.', ''), '0'), '.') ?: '0';
    }
}
