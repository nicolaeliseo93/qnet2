<?php

namespace App\Imports\Support;

use SplFileObject;

/**
 * Native CSV parsing (SplFileObject/fgetcsv) for the generic import engine
 * (spec 0012) — NO new dependency, only CSV (no Excel/.xlsx, per spec §out).
 *
 * Reads the header row and requires it to match the definition's declared
 * columns EXACTLY (same ids, same order — the frozen fixed-template contract:
 * no interactive column mapping), stripping a leading UTF-8 BOM first so a
 * file exported from Excel still matches. The remaining rows are yielded as
 * header-mapped associative arrays (column id => trimmed raw value).
 * IMPORT_MAX_ROWS is enforced server-side while reading, so a hostile or
 * oversized file is rejected before it can exhaust memory/CPU.
 */
final class CsvReader
{
    private const string BOM = "\xEF\xBB\xBF";

    /**
     * @param  array<int, string>  $expectedColumns  column ids, in declared order
     * @return array<int, array<string, string>> keyed by 1-based row number (data rows only, header excluded)
     *
     * @throws CsvReaderException
     */
    public function read(string $path, array $expectedColumns): array
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $this->assertHeaderMatches($this->readHeader($file), $expectedColumns);

        return $this->readDataRows($file, $expectedColumns);
    }

    /**
     * @return array<int, string|null>
     */
    private function readHeader(SplFileObject $file): array
    {
        $file->rewind();
        $header = $file->current();

        if (! is_array($header) || $header === [null]) {
            throw new CsvReaderException('The file has no header row.');
        }

        $header[0] = $this->stripBom((string) ($header[0] ?? ''));

        return array_map(static fn (mixed $value): string => trim((string) $value), $header);
    }

    private function stripBom(string $value): string
    {
        return str_starts_with($value, self::BOM) ? substr($value, strlen(self::BOM)) : $value;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $expected
     */
    private function assertHeaderMatches(array $header, array $expected): void
    {
        if ($header !== $expected) {
            throw new CsvReaderException(
                'The file header does not match the expected template columns: '.implode(',', $expected).'.'
            );
        }
    }

    /**
     * @param  array<int, string>  $expectedColumns
     * @return array<int, array<string, string>>
     */
    private function readDataRows(SplFileObject $file, array $expectedColumns): array
    {
        $maxRows = (int) config('imports.max_rows');
        $rows = [];
        $rowNumber = 0;

        // The header was already consumed by readHeader()'s rewind()+current();
        // advance past it once before reading data rows.
        $file->next();

        while ($file->valid()) {
            $line = $file->current();
            $file->next();

            if (! is_array($line) || $line === [null]) {
                continue;
            }

            $rowNumber++;

            if ($rowNumber > $maxRows) {
                throw new CsvReaderException("The file exceeds the maximum allowed number of rows ({$maxRows}).");
            }

            $rows[$rowNumber] = $this->mapRow($expectedColumns, $line);
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $expectedColumns
     * @param  array<int, string|null>  $line
     * @return array<string, string>
     */
    private function mapRow(array $expectedColumns, array $line): array
    {
        $row = [];

        foreach ($expectedColumns as $index => $columnId) {
            $row[$columnId] = trim((string) ($line[$index] ?? ''));
        }

        return $row;
    }
}
