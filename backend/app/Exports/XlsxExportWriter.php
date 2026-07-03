<?php

namespace App\Exports;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * openspout-backed XLSX writer (spec 0014, the one authorized new
 * dependency): streams rows straight to the ZIP/XML output, never building
 * the spreadsheet in memory.
 */
class XlsxExportWriter implements ExportWriter
{
    private ?Writer $writer = null;

    public function open(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        $this->writer = $writer;
    }

    public function writeHeaders(array $headers): void
    {
        $this->writer()->addRow(Row::fromValues($headers));
    }

    public function writeRow(array $cells): void
    {
        $this->writer()->addRow(Row::fromValues($cells));
    }

    public function close(): void
    {
        $this->writer?->close();
        $this->writer = null;
    }

    private function writer(): Writer
    {
        if ($this->writer === null) {
            throw new RuntimeException('XlsxExportWriter::open() must be called before writing.');
        }

        return $this->writer;
    }
}
