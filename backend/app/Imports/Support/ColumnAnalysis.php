<?php

namespace App\Imports\Support;

/**
 * Header + row-count snapshot produced by SpreadsheetReader::analyze() (spec
 * 0033): the wizard's mapping step is built directly off `columns` (persisted
 * verbatim as `import_runs.detected_columns`), and ColumnMapper consumes the
 * SAME shape to propose a mapping — so the two never disagree on what a
 * "column" is.
 */
final readonly class ColumnAnalysis
{
    /**
     * @param  array<int, array{name: string, index: int, duplicate: bool}>  $columns
     */
    public function __construct(
        public array $columns,
        public int $rowCount,
    ) {}

    /**
     * Deterministic, lossless key for each column in $columns, in the same
     * order: the first occurrence of a name keeps the bare name; every later
     * occurrence of a duplicate is suffixed with its 0-based file index
     * (`"{name}#{index}"`), so no raw/mapped value ever overwrites another.
     * Shared by SpreadsheetReader::rows() (row value keys) and ColumnMapper
     * (mapping/diagnostic keys), so the two always agree on the same column.
     *
     * @param  array<int, array{name: string, index: int, duplicate: bool}>  $columns
     * @return array<int, string> one key per $columns entry, same order
     */
    public static function columnKeys(array $columns): array
    {
        $occurrences = [];
        $keys = [];

        foreach ($columns as $column) {
            $name = $column['name'];
            $seen = $occurrences[$name] ?? 0;
            $occurrences[$name] = $seen + 1;

            $keys[] = $seen === 0 ? $name : "{$name}#{$column['index']}";
        }

        return $keys;
    }
}
