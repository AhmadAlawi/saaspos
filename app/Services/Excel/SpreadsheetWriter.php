<?php

namespace App\Services\Excel;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin wrapper around OpenSpout that every list-export action in the
 * admin uses. Two responsibilities:
 *
 *   1. Pick the right writer (CSV vs XLSX) from a short format string.
 *   2. Stream rows directly to the HTTP response — no temp files, no
 *      memory blow-up on tables with tens of thousands of rows
 *      (OpenSpout was chosen specifically for this; PhpSpreadsheet
 *      can't do it).
 *
 * Scientific-notation prevention (XLSX only):
 *   Any PHP string that looks like a phone number, barcode, or large
 *   numeric ID (6 or more consecutive digits, optional leading '+') is
 *   written with Excel's built-in "@" (Text) number format.  This stops
 *   Excel from treating "918765000000" as a float and rendering it as
 *   "9.18765E+11".  CSV ignores cell styles, so CSV output is unchanged.
 *
 * Usage from an action:
 *
 *   return app(SpreadsheetWriter::class)->stream(
 *       'categories-' . now()->format('Y-m-d') . '.csv',
 *       ['ID', 'Name', 'Parent', 'Active'],
 *       fn ($write) => $write([
 *           ['1', 'Snacks', '—', 'Yes'],
 *           ['2', 'Drinks', '—', 'Yes'],
 *       ])
 *   );
 *
 * The `$write` callback receives a function you can call once with all
 * rows, or many times with chunks — useful when you're paging through
 * a big query.
 */
class SpreadsheetWriter
{
    /** Map of filename-suffix → writer class. Add formats here later. */
    private const WRITERS = [
        'csv'  => CsvWriter::class,
        'xlsx' => XlsxWriter::class,
    ];

    /**
     * Excel number-format code for "plain text" (numFmtId = 49).
     * Applied to numeric-looking string cells in XLSX output so that
     * phone numbers, barcodes, and large IDs are never auto-converted.
     */
    private const TEXT_FORMAT = '@';

    /**
     * Strings matching this pattern get the TEXT_FORMAT style in XLSX:
     * optional leading '+', then 6 or more consecutive digits.
     * Covers E.164 phones, EAN/GTIN barcodes, and large numeric IDs.
     */
    private const FORCE_TEXT_PATTERN = '/^\+?\d{6,}$/';

    /**
     * Stream a spreadsheet to the browser as a download.
     *
     * @param string                                                              $filename     desired download filename (extension picks the format)
     * @param array<int, string>                                                  $headerRow    first row written; pass an empty array to skip
     * @param callable(callable(iterable<array>): void): void                    $rowsCallback receives a `write(rows)` function it should call to emit data
     */
    public function stream(string $filename, array $headerRow, callable $rowsCallback): StreamedResponse
    {
        $ext    = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $writer = $this->writerFor($ext);
        $isXlsx = $ext === 'xlsx';

        return new StreamedResponse(function () use ($writer, $headerRow, $rowsCallback, $isXlsx) {
            // Open to php://output so OpenSpout streams directly to the
            // response body; nothing is buffered to disk.
            $writer->openToFile('php://output');

            if (!empty($headerRow)) {
                $writer->addRow(Row::fromValues($headerRow));
            }

            $rowsCallback(function (iterable $rows) use ($writer, $isXlsx): void {
                foreach ($rows as $row) {
                    $writer->addRow($this->buildRow($row, $isXlsx));
                }
            });

            $writer->close();
        }, 200, [
            'Content-Type'        => $this->contentTypeFor($filename),
            'Content-Disposition' => 'attachment; filename="'.addslashes($filename).'"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * Write a spreadsheet to a file on disk instead of streaming it to the
     * browser. Same row-building (and scientific-notation guard) as
     * {@see stream()}; used by the scheduled-report runner, which needs a
     * file it can attach to an email rather than an HTTP response.
     *
     * @param string                                              $absPath      absolute path to write (extension picks the format)
     * @param array<int, string>                                  $headerRow    first row written; pass an empty array to skip
     * @param callable(callable(iterable<array>): void): void     $rowsCallback receives a `write(rows)` function it should call to emit data
     */
    public function writeToFile(string $absPath, array $headerRow, callable $rowsCallback): void
    {
        $ext    = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $writer = $this->writerFor($ext);
        $isXlsx = $ext === 'xlsx';

        $writer->openToFile($absPath);

        if (!empty($headerRow)) {
            $writer->addRow(Row::fromValues($headerRow));
        }

        $rowsCallback(function (iterable $rows) use ($writer, $isXlsx): void {
            foreach ($rows as $row) {
                $writer->addRow($this->buildRow($row, $isXlsx));
            }
        });

        $writer->close();
    }

    /**
     * Build an OpenSpout Row, applying the '@' text-format style to any
     * string cell that matches FORCE_TEXT_PATTERN when writing XLSX.
     * CSV rows are returned unmodified (styles have no effect in CSV).
     *
     * @param array<mixed> $values
     */
    private function buildRow(array $values, bool $isXlsx): Row
    {
        if (! $isXlsx) {
            return Row::fromValues($values);
        }

        $textStyle = new Style(format: self::TEXT_FORMAT);
        $cells     = [];

        foreach ($values as $value) {
            if (is_string($value) && preg_match(self::FORCE_TEXT_PATTERN, $value)) {
                $cells[] = Cell::fromValue($value, $textStyle);
            } else {
                $cells[] = Cell::fromValue($value);
            }
        }

        return new Row($cells);
    }

    /** Resolve a writer instance for the given extension. */
    private function writerFor(string $ext): WriterInterface
    {
        if (!isset(self::WRITERS[$ext])) {
            throw new \InvalidArgumentException(
                "Unsupported spreadsheet format '{$ext}'. Supported: ".implode(', ', array_keys(self::WRITERS))
            );
        }

        return new (self::WRITERS[$ext])();
    }

    private function contentTypeFor(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'csv'  => 'text/csv; charset=UTF-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}
