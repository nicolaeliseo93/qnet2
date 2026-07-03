<?php

use App\Enums\ExportFormat;
use App\Exports\CsvExportWriter;
use App\Exports\ExportWriterFactory;
use App\Exports\XlsxExportWriter;
use Tests\TestCase;

uses(TestCase::class);

it('resolves the configured writer class per format through the container', function () {
    $factory = app(ExportWriterFactory::class);

    expect($factory->make(ExportFormat::Csv))->toBeInstanceOf(CsvExportWriter::class)
        ->and($factory->make(ExportFormat::Xlsx))->toBeInstanceOf(XlsxExportWriter::class);
});

it('throws when the format has no registered writer', function () {
    config(['exports.writers' => []]);

    $factory = app(ExportWriterFactory::class);

    expect(fn () => $factory->make(ExportFormat::Csv))->toThrow(RuntimeException::class);
});
