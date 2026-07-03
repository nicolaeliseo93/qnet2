<?php

use App\Exports\CsvExportWriter;
use App\Exports\XlsxExportWriter;

return [

    /*
    |--------------------------------------------------------------------------
    | Generic Domain-driven Export Engine
    |--------------------------------------------------------------------------
    |
    | Unlike config/imports.php, this registry needs no per-domain definition
    | class: every registered `App\Tables\TableDefinition` (config/tables.php)
    | already exposes everything ExportService needs (baseQuery, columns,
    | mapRow, the SSRM allow-lists), so ANY table domain gets export "for
    | free". This file only holds the format catalogue + the magic-value
    | constants (spec 0014 constraints).
    |
    */

    // Enabled formats — drives CreateExportRequest's Rule::in() AND
    // ExportWriterFactory's registry key lookup below.
    'formats' => ['csv', 'xlsx'],

    // Private disk the generated files are written to (never public).
    'disk' => 'local',

    // Directory (on the disk above) generated files are stored under.
    'directory' => 'exports',

    // Defensive cap on the number of rows a single export may write
    // (memory/time). The query is capped, never silently truncated without
    // being reflected in `row_count`.
    'max_rows' => (int) env('EXPORT_MAX_ROWS', 100000),

    // Rows fetched per lazy() cursor chunk while streaming the query into the
    // writer, keeping memory constant regardless of dataset size.
    'chunk_size' => (int) env('EXPORT_CHUNK_SIZE', 1000),

    // PHP date() format applied to `datetime`-typed columns by
    // ExportValueFormatter.
    'datetime_format' => env('EXPORT_DATETIME_FORMAT', 'Y-m-d H:i:s'),

    // Format => ExportWriter implementation (OCP registry, resolved through
    // the container by ExportWriterFactory). Adding a format (e.g. pdf) is a
    // new writer class + one line here — no core change.
    'writers' => [
        'csv' => CsvExportWriter::class,
        'xlsx' => XlsxExportWriter::class,
    ],

];
