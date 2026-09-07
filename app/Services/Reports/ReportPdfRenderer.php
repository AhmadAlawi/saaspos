<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\File;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders a report (title + column headers + row arrays) to a branded,
 * print-ready A4 PDF via mPDF and streams it as a download. Shared by every
 * report export so the layout, header, and footer stay consistent.
 *
 * mPDF needs a writable scratch directory; we point it at
 * storage/app/mpdf (created on demand) so it never writes into vendor/ and
 * stays inside the paths a shared host allows.
 */
class ReportPdfRenderer
{
    /**
     * Render the report to raw PDF bytes. Shared by {@see stream()} (HTTP
     * download) and the scheduled-report runner, which writes the bytes to a
     * file so it can attach them to an email.
     *
     * @param  list<string>              $header
     * @param  list<array<int, string>>  $rows
     * @param  list<int>                  $moneyColumns  Column indices to render
     *         with the company currency format (symbol + separators). Only the
     *         PDF is formatted; CSV/XLSX keep the raw numbers for spreadsheet math.
     */
    public function render(string $title, array $header, array $rows, string $from, string $to, array $moneyColumns = []): string
    {
        $rows = self::formatMoneyColumns($rows, $moneyColumns);

        $rtl = in_array(app()->getLocale(), ['ar', 'he', 'ur', 'fa'], true);

        $tmp = storage_path('app/mpdf');
        File::ensureDirectoryExists($tmp);

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'tempDir'       => $tmp,
            'default_font'  => 'dejavusans',
            'margin_top'    => 32,
            'margin_bottom' => 18,
            'margin_left'   => 12,
            'margin_right'  => 12,
            'directionality' => $rtl ? 'rtl' : 'ltr',
        ]);

        $brand     = config('app.name') ?: 'POS';
        $generated = now()->format('d M Y H:i');

        $mpdf->SetHTMLHeader($this->headerHtml($brand, $title));
        $mpdf->SetHTMLFooter($this->footerHtml($generated));

        $html = view('exports.pdf.report-table', [
            'title'  => $title,
            'header' => $header,
            'rows'   => $rows,
            'from'   => $from,
            'to'     => $to,
            'rtl'    => $rtl,
        ])->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /**
     * @param  list<string>              $header
     * @param  list<array<int, string>>  $rows
     * @param  list<int>                  $moneyColumns
     */
    public function stream(string $filename, string $title, array $header, array $rows, string $from, string $to, array $moneyColumns = []): StreamedResponse
    {
        $pdf = $this->render($title, $header, $rows, $from, $to, $moneyColumns);

        return response()->streamDownload(
            fn () => print($pdf),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Re-render the given column indices with the company currency format
     * (symbol + thousands/decimal separators). A cell that isn't numeric
     * (blank spacer, a section label that landed in a money column) is left
     * untouched. The raw cells arrive as plain 4dp `.`-decimal strings from
     * each export's `fmt()`, so `(float)` reparses them cleanly.
     *
     * @param  list<array<int, string>>  $rows
     * @param  list<int>                 $moneyColumns
     * @return list<array<int, string>>
     */
    public static function formatMoneyColumns(array $rows, array $moneyColumns): array
    {
        if ($moneyColumns === []) {
            return $rows;
        }

        $cols = array_flip($moneyColumns);

        return array_map(function (array $row) use ($cols) {
            foreach ($row as $i => $cell) {
                if (isset($cols[$i]) && is_numeric($cell)) {
                    $row[$i] = format_money((float) $cell);
                }
            }

            return $row;
        }, $rows);
    }

    private function headerHtml(string $brand, string $title): string
    {
        return '<div style="border-bottom:1px solid #cbd5e1;padding-bottom:4px;font-family:dejavusans;">'
            .'<table width="100%"><tr>'
            .'<td style="font-size:12px;font-weight:bold;color:#0f172a;">'.e($brand).'</td>'
            .'<td style="text-align:right;font-size:10px;color:#64748b;">'.e($title).'</td>'
            .'</tr></table></div>';
    }

    private function footerHtml(string $generated): string
    {
        return '<div style="border-top:1px solid #e2e8f0;padding-top:3px;font-family:dejavusans;font-size:8px;color:#94a3b8;">'
            .'<table width="100%"><tr>'
            .'<td>'.e(__('reports.pdf.generated_at', ['time' => $generated])).'</td>'
            .'<td style="text-align:right;">{PAGENO} / {nbpg}</td>'
            .'</tr></table></div>';
    }
}
