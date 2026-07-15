<?php

namespace App\Imports\Support;

use DateTimeInterface;
use Generator;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvSpreadsheetReader;
use OpenSpout\Reader\XLSX\Reader as XlsxSpreadsheetReader;
use Throwable;

/**
 * Multi-format (xlsx/csv/xls) header+rows reader for the mapping-driven
 * import wizard (spec 0033). xlsx/csv/txt go through openspout — already a
 * project dependency, used by App\Exports (AC-006). The legacy binary `.xls`
 * (Excel 97-2003) that openspout cannot parse is delegated to XlsRowReader,
 * built on `phpoffice/phpspreadsheet` (dependency authorized by the user
 * 2026-07-15). Unlike the legacy CsvReader (fixed template, exact header
 * match, CSV only), this reader is format-agnostic and exposes whatever
 * header the file actually carries, so ColumnMapper/the wizard's mapping step
 * can be built on top; the 5 legacy domains keep using CsvReader untouched.
 *
 * Every supported format is normalized to the SAME shape — trimmed string
 * cells, one associative row per data row keyed by the column keys
 * ColumnAnalysis derives from the header — so a caller cannot tell which
 * format produced a given row (the xlsx/csv/xls parity AC-006 requires). A
 * structurally duplicated header cell (byte-identical name) is flagged in
 * `columns`, distinct from ColumnMapper's normalized/alias matching against
 * the domain's mappable fields. No formula is ever evaluated (openspout reads
 * values, not formulas; the xls path is opened with `setReadDataOnly(true)`)
 * and rows beyond `imports.max_rows` abort the read defensively.
 */
final class SpreadsheetReader
{
    /** @var array<int, string> extensions this reader accepts, matched case-insensitively */
    private const array SUPPORTED_EXTENSIONS = ['csv', 'txt', 'xlsx', 'xls'];

    public function __construct(private readonly XlsRowReader $xlsRowReader = new XlsRowReader) {}

    /**
     * Header + row-count only, capped at `imports.max_rows` while reading —
     * a huge file is rejected before the caller ever asks for its rows.
     */
    public function analyze(string $absolutePath): ColumnAnalysis
    {
        $maxRows = (int) config('imports.max_rows');

        // Step 1: consume the header, then count data rows up to the cap
        $header = null;
        $rowCount = 0;

        foreach ($this->dataCells($absolutePath, $maxRows) as $cells) {
            if ($header === null) {
                $header = $cells;

                continue;
            }

            if ($this->isBlank($cells)) {
                continue;
            }

            $rowCount++;
            $this->assertWithinRowCap($rowCount, $maxRows);
        }

        // Step 2: a file with no header row at all cannot be analyzed
        if ($header === null) {
            throw new SpreadsheetReaderException('The file has no header row.');
        }

        return new ColumnAnalysis($this->buildColumns($header), $rowCount);
    }

    /**
     * Data rows only (header excluded), keyed by 1-based row number, each
     * mapped to its ColumnAnalysis::columnKeys() column key. A generator: the
     * file is streamed row by row, never fully loaded in memory.
     *
     * @return Generator<int, array<string, string>>
     */
    public function rows(string $absolutePath): Generator
    {
        $maxRows = (int) config('imports.max_rows');
        $keys = null;
        $rowNumber = 0;

        foreach ($this->dataCells($absolutePath, $maxRows) as $cells) {
            if ($keys === null) {
                $keys = ColumnAnalysis::columnKeys($this->buildColumns($cells));

                continue;
            }

            if ($this->isBlank($cells)) {
                continue;
            }

            $rowNumber++;
            $this->assertWithinRowCap($rowNumber, $maxRows);

            yield $rowNumber => $this->mapRow($keys, $cells);
        }

        if ($keys === null) {
            throw new SpreadsheetReaderException('The file has no header row.');
        }
    }

    private function assertWithinRowCap(int $rowNumber, int $maxRows): void
    {
        if ($rowNumber > $maxRows) {
            throw new SpreadsheetReaderException("The file exceeds the maximum allowed number of rows ({$maxRows}).");
        }
    }

    /**
     * Dispatches by extension to the format-specific engine and yields every
     * row of the file's FIRST sheet (multi-sheet workbooks are out of scope),
     * each as a plain list of trimmed string cell values — the single shape
     * every downstream engine (openspout, XlsRowReader) normalizes to.
     *
     * @return iterable<int, array<int, string>>
     */
    private function dataCells(string $absolutePath, int $maxRows): iterable
    {
        $extension = Str::lower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            throw new SpreadsheetReaderException("Unsupported spreadsheet extension \"{$extension}\".");
        }

        if ($extension === 'xls') {
            yield from $this->xlsRowReader->cells($absolutePath, $maxRows);

            return;
        }

        yield from $this->openSpoutCells($absolutePath, $extension);
    }

    /**
     * @return iterable<int, array<int, string>>
     */
    private function openSpoutCells(string $absolutePath, string $extension): iterable
    {
        $reader = $extension === 'xlsx' ? new XlsxSpreadsheetReader : new CsvSpreadsheetReader;

        try {
            $reader->open($absolutePath);
        } catch (Throwable $exception) {
            throw new SpreadsheetReaderException("The file could not be read: {$exception->getMessage()}.", previous: $exception);
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield $this->cellsToStrings($row);
                }

                return;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function cellsToStrings(Row $row): array
    {
        return array_map($this->cellToString(...), array_values($row->toArray()));
    }

    private function cellToString(mixed $value): string
    {
        return trim(match (true) {
            $value === null => '',
            is_string($value) => $value,
            is_bool($value) => $value ? '1' : '0',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => (string) $value,
        });
    }

    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $header
     * @return array<int, array{name: string, index: int, duplicate: bool}>
     */
    private function buildColumns(array $header): array
    {
        $counts = array_count_values($header);

        $columns = [];
        foreach ($header as $index => $name) {
            $columns[] = [
                'name' => $name,
                'index' => $index,
                'duplicate' => $counts[$name] > 1,
            ];
        }

        return $columns;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $cells
     * @return array<string, string>
     */
    private function mapRow(array $keys, array $cells): array
    {
        $row = [];

        foreach ($keys as $index => $key) {
            $row[$key] = $cells[$index] ?? '';
        }

        return $row;
    }
}
