<?php

namespace App\Actions\Reports;

use App\Actions\Reports\Concerns\StreamsReportExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Stream the General Ledger for one account as CSV / XLSX / PDF. */
class ExportGeneralLedger
{
    use StreamsReportExport;

    /** Debit, Credit, Balance. @return list<int> */
    public function moneyColumns(): array
    {
        return [4, 5, 6];
    }

    /**
     * @param  array{account: ?\App\Models\Account, opening: string, rows: list<array<string, mixed>>}  $result
     * @return array{0: list<string>, 1: list<array<int, string>>}
     */
    public function build(array $result): array
    {
        $header = apply_filters('reports.general_ledger.export.header', [
            'Date', 'Entry', 'Source', 'Description', 'Debit', 'Credit', 'Balance',
        ]);

        $out   = [];
        $out[] = ['', '', '', __('reports.general_ledger.opening_balance'), '', '', $this->fmt($result['opening'])];

        foreach ($result['rows'] as $r) {
            $out[] = apply_filters('reports.general_ledger.export.row', [
                (string) $r['date'],
                (string) $r['number'],
                (string) $r['source'],
                (string) $r['description'],
                $this->fmt($r['debit']),
                $this->fmt($r['credit']),
                $this->fmt($r['balance']),
            ], $r);
        }

        return [$header, $out];
    }

    /** @param array<string, mixed> $result */
    public function __invoke(array $result, string $from, string $to, string $format = 'csv'): StreamedResponse
    {
        [$header, $out] = $this->build($result);

        $title = __('reports.general_ledger.title');
        if ($result['account'] ?? null) {
            $title .= ' — '.$result['account']->code.' '.$result['account']->name;
        }

        return $this->respondWithReportExport('general-ledger', $title, $header, $out, $format, $from, $to);
    }

    private function fmt(string|float|int|null $v): string
    {
        return rtrim(rtrim(number_format((float) ($v ?? 0), 4, '.', ''), '0'), '.') ?: '0';
    }
}
