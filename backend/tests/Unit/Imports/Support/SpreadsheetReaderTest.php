<?php

use App\Exports\XlsxExportWriter;
use App\Imports\Support\SpreadsheetReader;
use App\Imports\Support\SpreadsheetReaderException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls as XlsWriter;
use Tests\TestCase;

uses(TestCase::class);

function writeTempSpreadsheetCsv(string $content): string
{
    $path = sys_get_temp_dir().'/'.uniqid('spreadsheet-reader-', true).'.csv';
    file_put_contents($path, $content);

    return $path;
}

/**
 * @param  array<int, array<int, string>>  $rows  header row first, then data rows
 */
function writeTempSpreadsheetXlsx(array $rows): string
{
    $path = sys_get_temp_dir().'/'.uniqid('spreadsheet-reader-', true).'.xlsx';
    $writer = new XlsxExportWriter;

    $writer->open($path);
    $writer->writeHeaders($rows[0]);
    foreach (array_slice($rows, 1) as $row) {
        $writer->writeRow($row);
    }
    $writer->close();

    return $path;
}

/**
 * Legacy binary `.xls` (Excel 97-2003) fixture writer for the parity tests
 * below, via the same `phpoffice/phpspreadsheet` package XlsRowReader reads
 * with (spec 0033).
 *
 * @param  array<int, array<int, string>>  $rows  header row first, then data rows
 */
function writeTempSpreadsheetXls(array $rows): string
{
    $path = sys_get_temp_dir().'/'.uniqid('spreadsheet-reader-', true).'.xls';
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    foreach ($rows as $rowIndex => $row) {
        foreach ($row as $columnIndex => $value) {
            $sheet->setCellValue([$columnIndex + 1, $rowIndex + 1], $value);
        }
    }

    (new XlsWriter($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    return $path;
}

/**
 * A file carrying a `.xls` extension whose CONTENT is not an OLE/BIFF binary
 * but an OOXML (.xlsx) workbook — the "not recognised as an OLE file" case:
 * legacy systems routinely export spreadsheets under a `.xls` name that are
 * actually xlsx/HTML/XML. XlsRowReader must dispatch by content, not extension.
 *
 * @param  array<int, array<int, string>>  $rows  header row first, then data rows
 */
function writeTempXlsxRenamedToXls(array $rows): string
{
    $xlsPath = sys_get_temp_dir().'/'.uniqid('spreadsheet-reader-', true).'.xls';
    copy(writeTempSpreadsheetXlsx($rows), $xlsPath);

    return $xlsPath;
}

// ---------------------------------------------------------------------------
// AC-006 — SpreadsheetReader: xlsx/csv parity, duplicate header, row cap, BOM
// ---------------------------------------------------------------------------

it('reads the same header and rows from an xlsx and a csv with the same content', function () {
    $csvPath = writeTempSpreadsheetCsv("Full Name,Email\nMario Rossi,mario@example.com\nLuca Bianchi,luca@example.com\n");
    $xlsxPath = writeTempSpreadsheetXlsx([
        ['Full Name', 'Email'],
        ['Mario Rossi', 'mario@example.com'],
        ['Luca Bianchi', 'luca@example.com'],
    ]);

    $reader = new SpreadsheetReader;

    $csvAnalysis = $reader->analyze($csvPath);
    $xlsxAnalysis = $reader->analyze($xlsxPath);

    expect($csvAnalysis->columns)->toBe($xlsxAnalysis->columns)
        ->and($csvAnalysis->rowCount)->toBe($xlsxAnalysis->rowCount)->toBe(2);

    $csvRows = iterator_to_array($reader->rows($csvPath));
    $xlsxRows = iterator_to_array($reader->rows($xlsxPath));

    expect($csvRows)->toBe($xlsxRows)
        ->and($csvRows)->toBe([
            1 => ['Full Name' => 'Mario Rossi', 'Email' => 'mario@example.com'],
            2 => ['Full Name' => 'Luca Bianchi', 'Email' => 'luca@example.com'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-006 (extended) — .xls (Excel 97-2003 binary, phpoffice/phpspreadsheet)
// parity with csv/xlsx, per spec 0033's multi-format parsing update.
// ---------------------------------------------------------------------------

it('reads the same header and rows from an xls and a csv with the same content', function () {
    $csvPath = writeTempSpreadsheetCsv("Full Name,Email\nMario Rossi,mario@example.com\nLuca Bianchi,luca@example.com\n");
    $xlsPath = writeTempSpreadsheetXls([
        ['Full Name', 'Email'],
        ['Mario Rossi', 'mario@example.com'],
        ['Luca Bianchi', 'luca@example.com'],
    ]);

    $reader = new SpreadsheetReader;

    $csvAnalysis = $reader->analyze($csvPath);
    $xlsAnalysis = $reader->analyze($xlsPath);

    expect($csvAnalysis->columns)->toBe($xlsAnalysis->columns)
        ->and($csvAnalysis->rowCount)->toBe($xlsAnalysis->rowCount)->toBe(2);

    $csvRows = iterator_to_array($reader->rows($csvPath));
    $xlsRows = iterator_to_array($reader->rows($xlsPath));

    expect($xlsRows)->toBe($csvRows)
        ->and($xlsRows)->toBe([
            1 => ['Full Name' => 'Mario Rossi', 'Email' => 'mario@example.com'],
            2 => ['Full Name' => 'Luca Bianchi', 'Email' => 'luca@example.com'],
        ]);
});

it('reads a .xls-named file whose content is actually xlsx (mislabeled legacy export) via content auto-detection', function () {
    $path = writeTempXlsxRenamedToXls([
        ['Full Name', 'Email'],
        ['Mario Rossi', 'mario@example.com'],
        ['Luca Bianchi', 'luca@example.com'],
    ]);

    $reader = new SpreadsheetReader;

    expect($reader->analyze($path)->rowCount)->toBe(2)
        ->and(iterator_to_array($reader->rows($path)))->toBe([
            1 => ['Full Name' => 'Mario Rossi', 'Email' => 'mario@example.com'],
            2 => ['Full Name' => 'Luca Bianchi', 'Email' => 'luca@example.com'],
        ]);
});

it('detects a duplicated header column in an xls file and keeps both values addressable', function () {
    $path = writeTempSpreadsheetXls([
        ['Email', 'Phone', 'Email'],
        ['a@example.com', '123', 'b@example.com'],
    ]);

    $analysis = (new SpreadsheetReader)->analyze($path);

    expect($analysis->columns)->toBe([
        ['name' => 'Email', 'index' => 0, 'duplicate' => true],
        ['name' => 'Phone', 'index' => 1, 'duplicate' => false],
        ['name' => 'Email', 'index' => 2, 'duplicate' => true],
    ]);

    $rows = iterator_to_array((new SpreadsheetReader)->rows($path));

    expect($rows)->toBe([
        1 => ['Email' => 'a@example.com', 'Phone' => '123', 'Email#2' => 'b@example.com'],
    ]);
});

it('throws when an xls file exceeds the configured row cap during analyze', function () {
    config(['imports.max_rows' => 2]);
    $path = writeTempSpreadsheetXls([
        ['name', 'type'],
        ['A', ''],
        ['B', ''],
        ['C', ''],
    ]);

    expect(fn () => (new SpreadsheetReader)->analyze($path))
        ->toThrow(SpreadsheetReaderException::class);
});

it('throws when an xls file exceeds the configured row cap while streaming rows', function () {
    config(['imports.max_rows' => 2]);
    $path = writeTempSpreadsheetXls([
        ['name', 'type'],
        ['A', ''],
        ['B', ''],
        ['C', ''],
    ]);

    expect(fn () => iterator_to_array((new SpreadsheetReader)->rows($path)))
        ->toThrow(SpreadsheetReaderException::class);
});

it('detects a duplicated header column and keeps both values addressable', function () {
    $path = writeTempSpreadsheetCsv("Email,Phone,Email\na@example.com,123,b@example.com\n");

    $analysis = (new SpreadsheetReader)->analyze($path);

    expect($analysis->columns)->toBe([
        ['name' => 'Email', 'index' => 0, 'duplicate' => true],
        ['name' => 'Phone', 'index' => 1, 'duplicate' => false],
        ['name' => 'Email', 'index' => 2, 'duplicate' => true],
    ]);

    $rows = iterator_to_array((new SpreadsheetReader)->rows($path));

    expect($rows)->toBe([
        1 => ['Email' => 'a@example.com', 'Phone' => '123', 'Email#2' => 'b@example.com'],
    ]);
});

it('throws when the file exceeds the configured row cap during analyze', function () {
    config(['imports.max_rows' => 2]);
    $path = writeTempSpreadsheetCsv("name,type\nA,\nB,\nC,\n");

    expect(fn () => (new SpreadsheetReader)->analyze($path))
        ->toThrow(SpreadsheetReaderException::class);
});

it('throws when the file exceeds the configured row cap while streaming rows', function () {
    config(['imports.max_rows' => 2]);
    $path = writeTempSpreadsheetCsv("name,type\nA,\nB,\nC,\n");

    expect(fn () => iterator_to_array((new SpreadsheetReader)->rows($path)))
        ->toThrow(SpreadsheetReaderException::class);
});

it('accepts exactly the configured row cap without throwing', function () {
    config(['imports.max_rows' => 2]);
    $path = writeTempSpreadsheetCsv("name,type\nA,\nB,\n");

    $analysis = (new SpreadsheetReader)->analyze($path);

    expect($analysis->rowCount)->toBe(2);
});

it('strips a leading UTF-8 BOM from the csv header before analyzing', function () {
    $path = writeTempSpreadsheetCsv("\xEF\xBB\xBFName,Type\nSales,Business\n");

    $analysis = (new SpreadsheetReader)->analyze($path);

    expect($analysis->columns[0]['name'])->toBe('Name');

    $rows = iterator_to_array((new SpreadsheetReader)->rows($path));

    expect($rows[1])->toBe(['Name' => 'Sales', 'Type' => 'Business']);
});

it('skips fully blank rows', function () {
    $path = writeTempSpreadsheetCsv("name,type\nSales,\n,\nSupport,\n");

    $rows = iterator_to_array((new SpreadsheetReader)->rows($path));

    expect($rows)->toHaveCount(2)
        ->and(array_values($rows))->toBe([
            ['name' => 'Sales', 'type' => ''],
            ['name' => 'Support', 'type' => ''],
        ]);
});

it('throws when the file has no header row (empty file)', function () {
    $path = writeTempSpreadsheetCsv('');

    expect(fn () => (new SpreadsheetReader)->analyze($path))
        ->toThrow(SpreadsheetReaderException::class);
    expect(fn () => iterator_to_array((new SpreadsheetReader)->rows($path)))
        ->toThrow(SpreadsheetReaderException::class);
});

it('throws for an unsupported file extension', function () {
    expect(fn () => (new SpreadsheetReader)->analyze('/tmp/whatever.doc'))
        ->toThrow(SpreadsheetReaderException::class);
});
