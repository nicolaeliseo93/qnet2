<?php

namespace App\Imports\Support;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads a file carrying the legacy `.xls` extension via
 * `phpoffice/phpspreadsheet` (dependency authorized by the user 2026-07-15 —
 * spec 0033), for the formats openspout cannot parse. Isolated behind
 * SpreadsheetReader so the heavier PhpSpreadsheet API (whole-workbook object
 * model, no true streaming) never leaks into the openspout-based xlsx/csv path.
 *
 * The reader is chosen by CONTENT auto-detection (IOFactory::
 * createReaderForFile), not the extension: many files carrying a `.xls`
 * extension are NOT true OLE/BIFF binaries but HTML tables, SpreadsheetML XML,
 * a renamed .xlsx, or CSV emitted by legacy systems — forcing the OLE-only Xls
 * reader throws "not recognised as an OLE file" on all of them. Auto-detection
 * dispatches each to its correct PhpSpreadsheet reader.
 *
 * Cells are read data-only (no formula evaluation) and yielded as the SAME
 * plain-list-of-trimmed-strings shape SpreadsheetReader derives from
 * openspout, so `rows()`/`analyze()` produce byte-identical output regardless
 * of which engine parsed the file (AC-006 parity extended to xls).
 *
 * PhpSpreadsheet has no streaming reader, so a row-bounded IReadFilter caps how
 * many rows are EVER loaded into the in-memory workbook: at most `$maxRows` + 2
 * (the header row, plus one row past the cap so the caller can still detect and
 * reject an oversized file) — an oversized file is rejected without ever
 * materializing it in full.
 */
final class XlsRowReader
{
    /**
     * @return iterable<int, array<int, string>>
     */
    public function cells(string $absolutePath, int $maxRows): iterable
    {
        try {
            $spreadsheet = $this->newReader($absolutePath, $maxRows)->load($absolutePath);
        } catch (Throwable $exception) {
            throw new SpreadsheetReaderException("The file could not be read: {$exception->getMessage()}.", previous: $exception);
        }

        try {
            yield from $this->iterateSheet($spreadsheet->getSheet(0));
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function newReader(string $absolutePath, int $maxRows): IReader
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setReadFilter($this->rowCapFilter($maxRows));

        return $reader;
    }

    /**
     * Rejects every cell past row `$maxRows` + 2 (1-based: header + one row
     * past the cap) so PhpSpreadsheet never builds a worksheet larger than
     * what the caller is willing to accept.
     */
    private function rowCapFilter(int $maxRows): IReadFilter
    {
        return new class($maxRows) implements IReadFilter
        {
            public function __construct(private readonly int $maxRows) {}

            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= $this->maxRows + 2;
            }
        };
    }

    /**
     * @return iterable<int, array<int, string>>
     */
    private function iterateSheet(Worksheet $sheet): iterable
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            $cells = [];
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $coordinate = Coordinate::stringFromColumnIndex($column).$row;
                $cells[] = $this->cellToString($sheet->getCell($coordinate));
            }

            yield $cells;
        }
    }

    private function cellToString(Cell $cell): string
    {
        $value = $cell->getValue();

        return trim(match (true) {
            $value === null => '',
            is_string($value) => $value,
            is_bool($value) => $value ? '1' : '0',
            is_numeric($value) && ExcelDate::isDateTime($cell) => ExcelDate::excelToDateTimeObject($value)->format('Y-m-d H:i:s'),
            default => (string) $value,
        });
    }
}
