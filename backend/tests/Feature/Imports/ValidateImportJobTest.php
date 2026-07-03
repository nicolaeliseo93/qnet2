<?php

use App\Enums\ImportStatus;
use App\Imports\ImportRegistry;
use App\Imports\Support\CsvReader;
use App\Jobs\ValidateImportJob;
use App\Models\BusinessFunction;
use App\Models\ImportRun;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Stubs\StubImportDefinition;

uses(RefreshDatabase::class);

function runValidateImportJob(ImportRun $run): void
{
    (new ValidateImportJob($run->id))
        ->handle(app(ImportRegistry::class), app(CsvReader::class), app(ImportService::class));
}

/**
 * Phase 1 (dry-run) engine test: exercises CsvReader + ImportRowProcessor +
 * ImportPreview + ImportService::writeErrorReport + the status transition,
 * generically (via the stub definition) — no real per-module definition is
 * registered yet (follow-up gate).
 */
it('validates every row, persists counts/preview, writes the report, moves to awaiting_confirmation', function () {
    config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class]]);
    Storage::fake('local');

    BusinessFunction::factory()->create(['name' => 'Support']);

    $csv = "name,type\n"
        ."Sales,business_unit\n" // valid
        .",\n" // invalid: name required
        ."Sales,business_unit\n" // invalid: intra-file duplicate of row 1
        ."Support,\n"; // invalid: duplicate of an existing DB row
    Storage::disk('local')->put('imports/upload.csv', $csv);

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'status' => ImportStatus::Validating,
        'stored_path' => 'imports/upload.csv',
    ]);

    runValidateImportJob($run);

    $fresh = $run->fresh();

    expect($fresh->status)->toBe(ImportStatus::AwaitingConfirmation)
        ->and($fresh->total_rows)->toBe(4)
        ->and($fresh->valid_rows)->toBe(1)
        ->and($fresh->invalid_rows)->toBe(3)
        ->and($fresh->preview['columns'])->toBe(['name', 'type'])
        ->and($fresh->preview['valid_sample'])->toHaveCount(1)
        ->and($fresh->preview['invalid_sample'])->toHaveCount(3)
        ->and($fresh->error_report_path)->not->toBeNull();

    Storage::disk('local')->assertExists($fresh->error_report_path);
    $report = Storage::disk('local')->get($fresh->error_report_path);
    $lines = array_filter(explode("\n", trim($report)));
    expect($lines[0])->toBe('name,type,row_number,errors')
        ->and($lines)->toHaveCount(4); // header + 3 rejected rows

    // Phase 1 is dry-run only: no BusinessFunction was created.
    expect(BusinessFunction::query()->count())->toBe(1);
});

it('moves the run to failed on an unhandled exception (e.g. unknown domain)', function () {
    config(['imports.definitions' => []]); // stub-widgets NOT registered
    Storage::fake('local');
    Storage::disk('local')->put('imports/upload.csv', "name,type\nSales,\n");

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'status' => ImportStatus::Validating,
        'stored_path' => 'imports/upload.csv',
    ]);

    expect(fn () => runValidateImportJob($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(ImportStatus::Failed);
});

it('moves the run to failed when the CSV header does not match (CsvReaderException)', function () {
    config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class]]);
    Storage::fake('local');
    Storage::disk('local')->put('imports/upload.csv', "wrong,header\nSales,\n");

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'status' => ImportStatus::Validating,
        'stored_path' => 'imports/upload.csv',
    ]);

    expect(fn () => runValidateImportJob($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(ImportStatus::Failed);
});
