<?php

use App\Enums\ImportStatus;
use App\Models\BusinessFunction;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * BusinessFunctionsImportDefinition, end-to-end through the real registered
 * `business-functions` domain (config/imports.php) — spec 0012 AC-011/012.
 *
 * runValidateImportJob()/runProcessImportJob() are declared (unguarded) in
 * ValidateImportJobTest.php/ProcessImportJobTest.php and are globally
 * available across the Feature/Imports suite.
 */
it('AC-011: dry-run validates every row, counts are correct, NOTHING is created', function () {
    Storage::fake('local');
    BusinessFunction::factory()->create(['name' => 'Support']);

    $csv = "name,type\n"
        ."Sales,business_unit\n" // valid
        .",\n" // invalid: name required
        ."Sales,business_unit\n" // invalid: intra-file duplicate of row 1
        ."Support,\n"; // invalid: duplicate of an existing DB row
    Storage::disk('local')->put('imports/business-functions.csv', $csv);

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'business-functions',
        'status' => ImportStatus::Validating,
        'stored_path' => 'imports/business-functions.csv',
    ]);

    runValidateImportJob($run);

    $fresh = $run->fresh();

    expect($fresh->status)->toBe(ImportStatus::AwaitingConfirmation)
        ->and($fresh->total_rows)->toBe(4)
        ->and($fresh->valid_rows)->toBe(1)
        ->and($fresh->invalid_rows)->toBe(3)
        ->and($fresh->preview['valid_sample'][0]['name'])->toBe('Sales')
        ->and($fresh->preview['invalid_sample'])->toHaveCount(3);

    // Only the pre-existing "Support" row exists: phase 1 creates nothing.
    expect(BusinessFunction::query()->count())->toBe(1);
});

it('AC-012: commit creates ONLY the valid rows via BusinessFunctionService, imported_rows correct', function () {
    Storage::fake('local');
    BusinessFunction::factory()->create(['name' => 'Support']);

    $csv = "name,type\nSales,business_unit\n,\nSales,business_unit\nSupport,\n";
    Storage::disk('local')->put('imports/business-functions.csv', $csv);

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'business-functions',
        'status' => ImportStatus::Processing,
        'stored_path' => 'imports/business-functions.csv',
    ]);

    runProcessImportJob($run);

    $fresh = $run->fresh();

    expect($fresh->status)->toBe(ImportStatus::Completed)
        ->and($fresh->imported_rows)->toBe(1);

    expect(BusinessFunction::query()->count())->toBe(2); // pre-existing "Support" + new "Sales"
    $created = BusinessFunction::query()->where('name', 'Sales')->firstOrFail();
    expect($created->is_business_unit)->toBeTrue()
        ->and($created->is_business_service)->toBeFalse();
});

it('AC-016: an unhandled exception moves the run to failed (real domain, corrupt file)', function () {
    Storage::fake('local');
    Storage::disk('local')->put('imports/business-functions.csv', "wrong,header\nSales,\n");

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'business-functions',
        'status' => ImportStatus::Validating,
        'stored_path' => 'imports/business-functions.csv',
    ]);

    expect(fn () => runValidateImportJob($run))->toThrow(Exception::class);

    expect($run->fresh()->status)->toBe(ImportStatus::Failed);
});
