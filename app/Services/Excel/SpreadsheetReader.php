<?php

namespace App\Services\Excel;

use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Counterpart to {@see SpreadsheetWriter} — picks a CSV or XLSX reader
 * from a filename and yields rows as plain string arrays.
 *
 * Streaming on read too: we never load the whole sheet into memory.
 * The caller drives the iterator and decides how much of the file to
 * consume (preview reads the first N rows; commit reads everything).
 */
class SpreadsheetReader
{
    private const READERS = [
        'csv'  => CsvReader::class,
        'xlsx' => XlsxReader::class,
    ];

    /**
     * Yield every row of the sheet as a 0-indexed array of strings.
     * The first emitted item is the header row (whatever it looks
     * like in the file — caller decides whether to treat row 0 as
     * headers).
     *
     * @param string   $path  absolute filesystem path
     * @param int|null $limit max rows to yield, including header (null = no cap)
     *
     * @return \Generator<int, array<int, string>>
     */
    public function rows(string $path, ?int $limit = null): \Generator
    {
        $reader = $this->readerFor($path);
        $reader->open($path);

        $emitted = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if ($limit !== null && $emitted >= $limit) {
                        return;
                    }
                    $cells = array_map(
                        fn ($c) => trim((string) $c),
                        $row->toArray(),
                    );
                    yield $emitted => $cells;
                    $emitted++;
                }
                // Only read the first sheet for v1 imports — multi-sheet
                // CSV doesn't exist, and XLSX imports almost always want
                // the active / first sheet.
                break;
            }
        } finally {
            $reader->close();
        }
    }

    private function readerFor(string $path): ReaderInterface
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!isset(self::READERS[$ext])) {
            throw new \InvalidArgumentException(
                "Unsupported spreadsheet format '{$ext}'. Supported: ".implode(', ', array_keys(self::READERS))
            );
        }
        $class = self::READERS[$ext];
        return new $class();
    }
}
