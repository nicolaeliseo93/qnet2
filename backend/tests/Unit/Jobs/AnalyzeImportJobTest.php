<?php

use App\Enums\ImportStatus;
use App\Imports\ImportRegistry;
use App\Imports\Support\ColumnMapper;
use App\Imports\Support\SpreadsheetReader;
use App\Jobs\AnalyzeImportJob;
use App\Models\BusinessFunction;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\Jobs\Fixtures\FakeWizardImportDefinition;

// Touches the database, so bind the full TestCase + RefreshDatabase
// explicitly (the default Pest binding only applies to the Feature suite).
uses(TestCase::class, RefreshDatabase::class);

function runAnalyzeImportJob(ImportRun $run): void
{
    (new AnalyzeImportJob($run->id))
        ->handle(app(ImportRegistry::class), app(SpreadsheetReader::class), app(ColumnMapper::class));
}

// ---------------------------------------------------------------------------
// AC-007 — AnalyzeImportJob: detected_columns + suggested mapping, no writes
// ---------------------------------------------------------------------------

it('saves detected_columns and an auto-mapping proposal, moves to configuring, stages nothing', function () {
    config(['imports.definitions' => ['wizard-widgets' => FakeWizardImportDefinition::class]]);
    Storage::fake('local');

    $csv = "Full Name,Email\nMario Rossi,mario@test.com\nLuisa Bianchi,luisa@test.com\n";
    Storage::disk('local')->put('imports/upload.csv', $csv);

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'wizard-widgets',
        'status' => ImportStatus::Analyzing,
        'stored_path' => 'imports/upload.csv',
    ]);

    runAnalyzeImportJob($run);

    $fresh = $run->fresh();

    expect($fresh->status)->toBe(ImportStatus::Configuring)
        ->and($fresh->total_rows)->toBe(2)
        ->and($fresh->detected_columns)->toBe([
            ['name' => 'Full Name', 'index' => 0, 'duplicate' => false],
            ['name' => 'Email', 'index' => 1, 'duplicate' => false],
        ])
        ->and($fresh->column_mapping)->toBe(['Full Name' => 'full_name', 'Email' => 'email']);

    // Phase A never stages nor creates domain records.
    expect(ImportRunRow::query()->count())->toBe(0)
        ->and(BusinessFunction::query()->count())->toBe(0);
});

it('moves the run to failed on an unhandled exception (e.g. unknown domain)', function () {
    config(['imports.definitions' => []]); // wizard-widgets NOT registered
    Storage::fake('local');
    Storage::disk('local')->put('imports/upload.csv', "Full Name,Email\nMario Rossi,mario@test.com\n");

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'wizard-widgets',
        'status' => ImportStatus::Analyzing,
        'stored_path' => 'imports/upload.csv',
    ]);

    expect(fn () => runAnalyzeImportJob($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(ImportStatus::Failed);
});

it('moves the run to failed when the file has no header row', function () {
    config(['imports.definitions' => ['wizard-widgets' => FakeWizardImportDefinition::class]]);
    Storage::fake('local');
    Storage::disk('local')->put('imports/upload.csv', '');

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'wizard-widgets',
        'status' => ImportStatus::Analyzing,
        'stored_path' => 'imports/upload.csv',
    ]);

    expect(fn () => runAnalyzeImportJob($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(ImportStatus::Failed);
});
