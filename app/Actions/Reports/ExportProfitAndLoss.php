<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Profit & Loss statement as CSV / XLSX / PDF. */
class ExportProfitAndLoss
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
        $header = apply_filters('reports.pnl.export.header', [
            __('reports.pnl.columns.account'), __('reports.pnl.columns.amount'),
        ]);

        $rows = [];
        $this->section($rows, __('reports.pnl.income'), $data['income'], __('reports.pnl.total_income'));
        $this->section($rows, __('reports.pnl.cogs'), $data['cogs'], __('reports.pnl.total_cogs'));
        $rows[] = [__('reports.pnl.gross_profit'), $this->fmt($data['gross_profit'])];
        $this->section($rows, __('reports.pnl.operating'), $data['operating'], __('reports.pnl.total_operating'));
        $this->section($rows, __('reports.pnl.other'), $data['other'], __('reports.pnl.total_other'));
        $rows[] = [__('reports.pnl.total_expenses'), $this->fmt($data['total_expenses'])];
        $rows[] = [__('reports.pnl.net_profit'), $this->fmt($data['net_profit'])];

        return [$header, $rows];
    }

    /** @param array{rows: list<array<string,string>>, total: string} $section */
    private function section(array &$rows, string $label, array $section, string $totalLabel): void
    {
        $rows[] = [$label, ''];
        foreach ($section['rows'] as $r) {
            $rows[] = ['   '.$r['code'].' '.$r['name'], $this->fmt($r['amount'])];
        }
        $rows[] = [$totalLabel, $this->fmt($section['total'])];
    }

    /** @param array<string, mixed> $data */
    public function __invoke(array $data, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $rows] = $this->build($data);

        return $this->respondWithReportExport('profit-and-loss', __('reports.pnl.title'), $header, $rows, $format, $from, $to);
    }

    private function fmt(string|float|int|null $v): string
    {
        return rtrim(rtrim(number_format((float) ($v ?? 0), 4, '.', ''), '0'), '.') ?: '0';
    }
}
