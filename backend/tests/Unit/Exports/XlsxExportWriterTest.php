<?php

use App\Exports\XlsxExportWriter;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

function xlsxWriterTempPath(): string
{
    return sys_get_temp_dir().'/'.uniqid('xlsx-export-writer-', true).'.xlsx';
}

it('writes a re-openable .xlsx file with the header + data rows', function () {
    $path = xlsxWriterTempPath();
    $writer = new XlsxExportWriter;

    $writer->open($path);
    $writer->writeHeaders(['Name', 'Active']);
    $writer->writeRow(['Sales', 'Yes']);
    $writer->close();

    $reader = new XlsxReader;
    $reader->open($path);

    $values = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $values[] = $row->toArray();
        }
    }
    $reader->close();

    expect($values)->toBe([
        ['Name', 'Active'],
        ['Sales', 'Yes'],
    ]);

    unlink($path);
});

it('throws when writing before open()', function () {
    $writer = new XlsxExportWriter;

    expect(fn () => $writer->writeHeaders(['Name']))->toThrow(RuntimeException::class);
});
