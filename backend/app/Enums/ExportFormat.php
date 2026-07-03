<?php

namespace App\Enums;

/**
 * Supported export file formats (spec 0014). The single source of truth for
 * the file extension and the download `Content-Type`, so neither the
 * ExportWriter implementations nor ExportController hard-code either.
 */
enum ExportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public function extension(): string
    {
        return match ($this) {
            self::Csv => 'csv',
            self::Xlsx => 'xlsx',
        };
    }

    public function contentType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
