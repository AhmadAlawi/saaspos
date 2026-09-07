<?php

namespace App\Services\Reports;

use App\Services\Excel\SpreadsheetWriter;
use Illuminate\Support\Facades\File;

/**
 * Renders a built report (header + rows) to a file on disk under
 * storage/app/report-exports and returns the absolute path. The disk sibling
 * of {@see \App\Actions\Reports\Concerns\StreamsReportExport}, used by the
 * scheduled-report runner which needs a file to attach to an email rather
 * than an HTTP stream.
 */
class ReportFileRenderer
{
    public function __construct(
        private SpreadsheetWriter $spreadsheet,
        private ReportPdfRenderer $pdf,
    ) {}

    /**
     * @param  list<string>              $header
     * @param  list<array<int, string>>  $rows
     * @param  list<int>                 $moneyColumns  Money column indices (PDF only)
     * @return string  absolute path of the written file
     */
    public function render(string $baseName, string $title, array $header, array $rows, string $format, string $from, string $to, array $moneyColumns = []): string
    {
        $format = strtolower($format);
        if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            $format = 'csv';
        }

        $dir = storage_path('app/report-exports');
        File::ensureDirectoryExists($dir);

        // A per-render token keeps two schedules of the same report+range
        // firing in the same minute from clobbering each other's file.
        $filename = $this->safeName("{$baseName}-{$from}-to-{$to}-".uniqid()).'.'.$format;
        $path     = $dir.DIRECTORY_SEPARATOR.$filename;

        if ($format === 'pdf') {
            File::put($path, $this->pdf->render($title, $header, $rows, $from, $to, $moneyColumns));

            return $path;
        }

        $this->spreadsheet->writeToFile($path, $header, fn (callable $write) => $write($rows));

        return $path;
    }

    /** Collapse anything that isn't filename-safe to a dash. */
    private function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? 'report';
    }
}
