<?php

use App\Enums\ExportFormat;

it('exposes the file extension per format', function () {
    expect(ExportFormat::Csv->extension())->toBe('csv')
        ->and(ExportFormat::Xlsx->extension())->toBe('xlsx');
});

it('exposes the download Content-Type per format', function () {
    expect(ExportFormat::Csv->contentType())->toBe('text/csv')
        ->and(ExportFormat::Xlsx->contentType())
        ->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('backs each case with its lowercase value', function () {
    expect(ExportFormat::Csv->value)->toBe('csv')
        ->and(ExportFormat::Xlsx->value)->toBe('xlsx');
});
