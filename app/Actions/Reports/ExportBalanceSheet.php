<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the Balance Sheet as CSV / XLSX / PDF. */
class ExportBalanceSheet
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
        $header = apply_filters('reports.balance_sheet.export.header', [
            __('reports.balance_sheet.columns.account'), __('reports.balance_sheet.columns.amount'),
        ]);

        $rows = [];
        $this->section($rows, __('reports.balance_sheet.assets'), $data['assets'], __('reports.balance_sheet.total_assets'));
        $this->section($rows, __('reports.balance_sheet.liabilities'), $data['liabilities'], __('reports.balance_sheet.total_liabilities'));

        // Equity carries the accounts plus current earnings.
        $rows[] = [__('reports.balance_sheet.equity'), ''];
        foreach ($data['equity']['rows'] as $r) {
            $rows[] = ['   '.$r['code'].' '.$r['name'], $this->fmt($r['amount'])];
        }
        $rows[] = ['   '.__('reports.balance_sheet.current_earnings'), $this->fmt($data['net_income'])];
        $rows[] = [__('reports.balance_sheet.total_equity'), $this->fmt($data['total_equity'])];

        $rows[] = [__('reports.balance_sheet.total_liabilities_equity'), $this->fmt($data['total_liabilities_equity'])];

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
    public function __invoke(array $data, string $asOf, string $format = 'csv'): StreamedResponse
    {
        [$header, $rows] = $this->build($data);

        return $this->respondWithReportExport('balance-sheet', __('reports.balance_sheet.title'), $header, $rows, $format, $asOf, $asOf);
    }

    private function fmt(string|float|int|null $v): string
    {
        return rtrim(rtrim(number_format((float) ($v ?? 0), 4, '.', ''), '0'), '.') ?: '0';
    }
}
