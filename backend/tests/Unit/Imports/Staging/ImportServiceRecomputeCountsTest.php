<?php

use App\Enums\ImportRowStatus;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// ImportService::recomputeCounts() — derives every counter from the CURRENT
// import_run_rows set (used by StageImportJob after staging, and by the
// wizard's inline-edit endpoint after a single row is re-validated).
// ---------------------------------------------------------------------------

it('derives total/valid/warning/invalid/duplicate/modified from the staged rows', function () {
    $run = ImportRun::factory()->create();

    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 1, 'status' => ImportRowStatus::Valid]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 2, 'status' => ImportRowStatus::Valid, 'is_edited' => true]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 3, 'status' => ImportRowStatus::Warning]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 4, 'status' => ImportRowStatus::Error]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 5, 'status' => ImportRowStatus::Duplicate]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'row_number' => 6, 'status' => ImportRowStatus::Skipped]);

    app(ImportService::class)->recomputeCounts($run);

    $fresh = $run->fresh();

    expect($fresh->total_rows)->toBe(6)
        ->and($fresh->valid_rows)->toBe(2)
        ->and($fresh->warning_rows)->toBe(1)
        ->and($fresh->invalid_rows)->toBe(1)
        ->and($fresh->duplicate_rows)->toBe(1)
        ->and($fresh->modified_rows)->toBe(1);
});

it('resets counters to 0 when the run has no staged rows', function () {
    $run = ImportRun::factory()->create(['total_rows' => 5, 'valid_rows' => 3]);

    app(ImportService::class)->recomputeCounts($run);

    $fresh = $run->fresh();

    expect($fresh->total_rows)->toBe(0)
        ->and($fresh->valid_rows)->toBe(0)
        ->and($fresh->warning_rows)->toBe(0)
        ->and($fresh->invalid_rows)->toBe(0)
        ->and($fresh->duplicate_rows)->toBe(0)
        ->and($fresh->modified_rows)->toBe(0);
});
