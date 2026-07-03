<?php

use App\Imports\Support\CsvReader;
use App\Imports\Support\CsvReaderException;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Writes $content to a throwaway file and returns its path (cleaned up by the
 * caller's test, tmp dir is process-local anyway).
 */
function writeTempCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'import-csv-');
    file_put_contents($path, $content);

    return $path;
}

// ---------------------------------------------------------------------------
// AC-004 — CsvReader: happy path, wrong/missing header, row cap, BOM, quotes
// ---------------------------------------------------------------------------

it('reads rows mapped to the declared header, in order', function () {
    $path = writeTempCsv("name,type\nSales,business_unit\nSupport,business_service\n");

    $rows = (new CsvReader)->read($path, ['name', 'type']);

    expect($rows)->toHaveCount(2)
        ->and($rows[1])->toBe(['name' => 'Sales', 'type' => 'business_unit'])
        ->and($rows[2])->toBe(['name' => 'Support', 'type' => 'business_service']);
});

it('strips a leading UTF-8 BOM from the header before matching', function () {
    $path = writeTempCsv("\xEF\xBB\xBFname,type\nSales,\n");

    $rows = (new CsvReader)->read($path, ['name', 'type']);

    expect($rows)->toHaveCount(1)
        ->and($rows[1]['name'])->toBe('Sales');
});

it('handles quoted values containing the delimiter', function () {
    $path = writeTempCsv("name,type\n\"Sales, EMEA\",\n");

    $rows = (new CsvReader)->read($path, ['name', 'type']);

    expect($rows[1]['name'])->toBe('Sales, EMEA');
});

it('throws when the header is missing (empty file)', function () {
    $path = writeTempCsv('');

    expect(fn () => (new CsvReader)->read($path, ['name', 'type']))
        ->toThrow(CsvReaderException::class);
});

it('throws when the header does not match the expected columns', function () {
    $path = writeTempCsv("wrong,header\nSales,x\n");

    expect(fn () => (new CsvReader)->read($path, ['name', 'type']))
        ->toThrow(CsvReaderException::class);
});

it('throws when the header has the right columns in the wrong order', function () {
    $path = writeTempCsv("type,name\nbusiness_unit,Sales\n");

    expect(fn () => (new CsvReader)->read($path, ['name', 'type']))
        ->toThrow(CsvReaderException::class);
});

it('enforces IMPORT_MAX_ROWS', function () {
    config(['imports.max_rows' => 2]);
    $path = writeTempCsv("name,type\nA,\nB,\nC,\n");

    expect(fn () => (new CsvReader)->read($path, ['name', 'type']))
        ->toThrow(CsvReaderException::class);
});

it('accepts exactly IMPORT_MAX_ROWS rows', function () {
    config(['imports.max_rows' => 2]);
    $path = writeTempCsv("name,type\nA,\nB,\n");

    $rows = (new CsvReader)->read($path, ['name', 'type']);

    expect($rows)->toHaveCount(2);
});
