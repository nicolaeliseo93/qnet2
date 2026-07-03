<?php

namespace App\Exports;

use RuntimeException;
use SplFileObject;

/**
 * Native CSV writer (zero dependency, spec 0014): a UTF-8 BOM is written
 * first so Excel opens accented/special characters correctly, then every row
 * is streamed straight to disk via SplFileObject::fputcsv — never buffered.
 */
class CsvExportWriter implements ExportWriter
{
    /** UTF-8 byte-order mark, written first so Excel detects the encoding. */
    private const string BOM = "\xEF\xBB\xBF";

    /**
     * No escape character (RFC 4180 double-quote-only escaping): passed
     * explicitly to fputcsv() since PHP deprecates its implicit default.
     */
    private const string NO_ESCAPE = '';

    private ?SplFileObject $file = null;

    public function open(string $path): void
    {
        $this->file = new SplFileObject($path, 'w');
        $this->file->fwrite(self::BOM);
    }

    public function writeHeaders(array $headers): void
    {
        $this->file()->fputcsv($headers, ',', '"', self::NO_ESCAPE);
    }

    public function writeRow(array $cells): void
    {
        $this->file()->fputcsv($cells, ',', '"', self::NO_ESCAPE);
    }

    public function close(): void
    {
        $this->file = null;
    }

    private function file(): SplFileObject
    {
        if ($this->file === null) {
            throw new RuntimeException('CsvExportWriter::open() must be called before writing.');
        }

        return $this->file;
    }
}
