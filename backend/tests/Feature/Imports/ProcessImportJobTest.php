<?php

use App\Enums\ImportStatus;
use App\Imports\ImportRegistry;
use App\Imports\Support\CsvReader;
use App\Jobs\ProcessImportJob;
use App\Models\BusinessFunction;
use App\Models\ImportRun;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Stubs\StubImportDefinition;
use Tests\Stubs\StubIsolationImportDefinition;

uses(RefreshDatabase::class);

function runProcessImportJob(ImportRun $run): void
{
    (new ProcessImportJob($run->id))
        ->handle(app(ImportRegistry::class), app(CsvReader::class), app(ImportService::class));
}

/**
 * Phase 2 (commit) engine test: exercises re-validation + per-row-transaction
 * creation (delegated to BusinessFunctionService via the stub definition) +
 * imported_rows + the status transition, generically.
 */
it('creates ONLY the valid rows, updates imported_rows, moves to completed', function () {
    config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class]]);
    Storage::fake('local');

    $csv = "name,type\n"
        ."Sales,business_unit\n" // valid
        .",\n" // invalid: name required
        ."Sales,business_unit\n"; // invalid: intra-file duplicate of row 1
    Storage::disk('local')->put('imports/upload.csv', $csv);

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'status' => ImportStatus::Processing,
        'stored_path' => 'imports/upload.csv',
    ]);

    runProcessImportJob($run);

    $fresh = $run->fresh();

    expect($fresh->status)->toBe(ImportStatus::Completed)
        ->and($fresh->imported_rows)->toBe(1)
        ->and($fresh->error_report_path)->not->toBeNull();

    expect(BusinessFunction::query()->where('name', 'Sales')->count())->toBe(1);

    $report = Storage::disk('local')->get($fresh->error_report_path);
    $lines = array_filter(explode("\n", trim($report)));
    expect($lines)->toHaveCount(3); // header + 2 rejected rows
});

it('isolates a commit-time failure to its own row without blocking the others (AC-015 machinery)', function () {
    config(['imports.definitions' => ['stub-widgets-isolation' => StubIsolationImportDefinition::class]]);
    Storage::fake('local');

    // "Boom" is the sentinel name StubIsolationImportDefinition::createRow()
    // deliberately throws on, simulating a commit-time failure isolated to ONE
    // row (both "Sales" and "Support" are otherwise valid, never-before-seen).
    $csv = "name\nSales\nBoom\nSupport\n";
    Storage::disk('local')->put('imports/upload.csv', $csv);

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets-isolation',
        'status' => ImportStatus::Processing,
        'stored_path' => 'imports/upload.csv',
    ]);

    runProcessImportJob($run);

    $fresh = $run->fresh();

    expect($fresh->status)->toBe(ImportStatus::Completed)
        ->and($fresh->imported_rows)->toBe(2); // Sales + Support, NOT Boom

    expect(BusinessFunction::query()->count())->toBe(2)
        ->and(BusinessFunction::query()->where('name', 'Boom')->exists())->toBeFalse();

    $report = Storage::disk('local')->get($fresh->error_report_path);
    expect($report)->toContain('Boom')
        ->and($report)->toContain('Simulated commit-time failure.');
});

it('moves the run to failed on an unhandled exception (e.g. unknown domain)', function () {
    config(['imports.definitions' => []]);
    Storage::fake('local');
    Storage::disk('local')->put('imports/upload.csv', "name,type\nSales,\n");

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'status' => ImportStatus::Processing,
        'stored_path' => 'imports/upload.csv',
    ]);

    expect(fn () => runProcessImportJob($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(ImportStatus::Failed);
});
