<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Cash Flow statement as CSV / XLSX / PDF. */
class ExportCashFlow
{
    use StreamsReportExport;

    /** Amount column (section spacers are blank → skipped). @return list<int> */
    public function moneyColumns(): array
    {
        return [1];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(array $data): array
    {
        $header = apply_filters('reports.cash_flow.export.header', [
            __('reports.cash_flow.columns.item'), __('reports.cash_flow.columns.amount'),
        ]);

        $rows = [];
        $this->section($rows, __('reports.cash_flow.operating'), $data['operating'], __('reports.cash_flow.net_operating'));
        $this->section($rows, __('reports.cash_flow.investing'), $data['investing'], __('reports.cash_flow.net_investing'));
        $this->section($rows, __('reports.cash_flow.financing'), $data['financing'], __('reports.cash_flow.net_financing'));

        $rows[] = [__('reports.cash_flow.net_change'), $this->fmt($data['net_change'])];
        $rows[] = [__('reports.cash_flow.opening'), $this->fmt($data['opening'])];
        $rows[] = [__('reports.cash_flow.closing'), $this->fmt($data['closing'])];

        return [$header, $rows];
    }

    /** @param array{rows: list<array{label:string, amount:string}>, total: string} $section */
    private function section(array &$rows, string $label, array $section, string $totalLabel): void
    {
        $rows[] = [$label, ''];
        foreach ($section['rows'] as $r) {
            $rows[] = ['   '.$r['label'], $this->fmt($r['amount'])];
        }
        $rows[] = [$totalLabel, $this->fmt($section['total'])];
    }

    /** @param array<string, mixed> $data */
    public function __invoke(array $data, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $rows] = $this->build($data);

        return $this->respondWithReportExport('cash-flow', __('reports.cash_flow.title'), $header, $rows, $format, $from, $to);
    }

    private function fmt(string|float|int|null $v): string
    {
        return rtrim(rtrim(number_format((float) ($v ?? 0), 4, '.', ''), '0'), '.') ?: '0';
    }
}
