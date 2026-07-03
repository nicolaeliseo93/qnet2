<?php

use App\Exports\CsvExportWriter;

function csvWriterTempPath(): string
{
    return sys_get_temp_dir().'/'.uniqid('csv-export-writer-', true).'.csv';
}

it('writes a UTF-8 BOM, the header row, then each data row', function () {
    $path = csvWriterTempPath();
    $writer = new CsvExportWriter;

    $writer->open($path);
    $writer->writeHeaders(['Name', 'Active']);
    $writer->writeRow(['Sales', 'Yes']);
    $writer->writeRow(['Support', 'No']);
    $writer->close();

    $contents = file_get_contents($path);

    expect(str_starts_with($contents, "\xEF\xBB\xBF"))->toBeTrue();

    $withoutBom = substr($contents, 3);
    $lines = array_filter(explode("\n", trim($withoutBom)));

    $parse = static fn (string $line): array => str_getcsv($line, ',', '"', '');

    expect(array_map($parse, $lines))->toBe([
        ['Name', 'Active'],
        ['Sales', 'Yes'],
        ['Support', 'No'],
    ]);

    unlink($path);
});

it('throws when writing before open()', function () {
    $writer = new CsvExportWriter;

    expect(fn () => $writer->writeHeaders(['Name']))->toThrow(RuntimeException::class);
});
