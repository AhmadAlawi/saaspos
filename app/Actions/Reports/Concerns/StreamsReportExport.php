<?php

namespace App\Actions\Reports\Concerns;

use App\Services\Excel\SpreadsheetWriter;
use App\Services\Reports\ReportPdfRenderer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared exit point for the report export actions. Given a built header +
 * row set, streams it as CSV, XLSX (OpenSpout) or PDF (mPDF) based on the
 * requested format — so each report only builds its data once and PDF
 * support is added in one place.
 */
trait StreamsReportExport
{
    /**
     * Column indices (0-based, matching the built row shape) whose cells hold
     * money and should render with the company currency format in the PDF.
     * Overridden per export; defaults to none. Kept public so the scheduled
     * report runner ({@see \App\Services\Reports\ReportRunner}) can read it too.
     *
     * @return list<int>
     */
    public function moneyColumns(): array
    {
        return [];
    }

    /**
     * @param  list<string>              $header
     * @param  list<array<int, string>>  $rows
     */
    protected function respondWithReportExport(string $baseName, string $title, array $header, array $rows, string $format, string $from, string $to): StreamedResponse
    {
        $format = strtolower($format);

        if ($format === 'pdf') {
            return app(ReportPdfRenderer::class)->stream("{$baseName}-{$from}-to-{$to}.pdf", $title, $header, $rows, $from, $to, $this->moneyColumns());
        }

        $ext = $format === 'xlsx' ? 'xlsx' : 'csv';

        return app(SpreadsheetWriter::class)->stream(
            "{$baseName}-{$from}-to-{$to}.{$ext}",
            $header,
            fn (callable $write) => $write($rows),
        );
    }
}
