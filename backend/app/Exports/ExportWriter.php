<?php

namespace App\Exports;

/**
 * Streaming file writer contract for the generic export engine (spec 0014).
 *
 * Every implementation writes DIRECTLY to disk (never buffers the whole file
 * in memory), so ExportService can call writeRow() once per streamed model
 * with constant memory regardless of the exported row count. Adding a format
 * (e.g. PDF) is a new implementation + one config/exports.php `writers` entry
 * — no change to ExportService (OCP).
 */
interface ExportWriter
{
    /**
     * Open the writer against the given absolute filesystem path.
     */
    public function open(string $path): void;

    /**
     * Write the header row (already-resolved column labels, in order).
     *
     * @param  array<int, string>  $headers
     */
    public function writeHeaders(array $headers): void;

    /**
     * Write one data row (already-formatted scalar cell values, in order).
     *
     * @param  array<int, string|int|float|bool|null>  $cells
     */
    public function writeRow(array $cells): void;

    /**
     * Flush and close the underlying file handle.
     */
    public function close(): void;
}
