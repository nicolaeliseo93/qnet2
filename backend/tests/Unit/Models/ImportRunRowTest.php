<?php

use App\Enums\ImportRowStatus;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-002 — import_run_rows schema + relation + casts + cascade
// ---------------------------------------------------------------------------

it('creates the import_run_rows table with the expected columns and indexes', function () {
    expect(Schema::hasTable('import_run_rows'))->toBeTrue();
    expect(Schema::hasColumns('import_run_rows', [
        'id', 'import_run_id', 'row_number', 'raw_values', 'mapped_values',
        'extra_values', 'resolved', 'status', 'messages', 'duplicate_of_id',
        'is_edited', 'created_at', 'updated_at',
    ]))->toBeTrue();

    $indexes = collect(Schema::getIndexes('import_run_rows'))->pluck('columns');

    expect($indexes->contains(['import_run_id', 'status']))->toBeTrue()
        ->and($indexes->contains(['import_run_id', 'row_number']))->toBeTrue();
});

it('down() drops the table, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_15_090100_create_import_run_rows_table.php');

    $migration->down();
    expect(Schema::hasTable('import_run_rows'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('import_run_rows'))->toBeTrue();
});

it('belongs to an import run', function () {
    $run = ImportRun::factory()->create();
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);

    expect($row->importRun)->toBeInstanceOf(ImportRun::class)
        ->and($row->importRun->id)->toBe($run->id);
});

it('cascades delete from the parent import run', function () {
    $run = ImportRun::factory()->create();
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);

    $run->delete();

    expect(ImportRunRow::find($row->id))->toBeNull();
});

it('casts status to ImportRowStatus and json columns to array', function () {
    $row = ImportRunRow::factory()->create([
        'status' => ImportRowStatus::Warning,
        'raw_values' => ['Full Name' => 'Mario Rossi'],
        'mapped_values' => ['first_name' => 'Mario', 'last_name' => 'Rossi'],
        'extra_values' => ['Note interne' => 'VIP'],
        'resolved' => ['name_split_confidence' => 0.4],
        'messages' => ['Low-confidence name split.'],
    ]);

    $fresh = $row->fresh();

    expect($fresh->status)->toBeInstanceOf(ImportRowStatus::class)
        ->and($fresh->status)->toBe(ImportRowStatus::Warning)
        ->and($fresh->raw_values)->toBe(['Full Name' => 'Mario Rossi'])
        ->and($fresh->mapped_values)->toBe(['first_name' => 'Mario', 'last_name' => 'Rossi'])
        ->and($fresh->extra_values)->toBe(['Note interne' => 'VIP'])
        ->and($fresh->resolved)->toBe(['name_split_confidence' => 0.4])
        ->and($fresh->messages)->toBe(['Low-confidence name split.']);
});

it('casts is_edited to bool and defaults to false at the database level', function () {
    $run = ImportRun::factory()->create();

    $id = DB::table('import_run_rows')->insertGetId([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'raw_values' => json_encode(['email' => 'a@b.com']),
        'mapped_values' => json_encode(['email' => 'a@b.com']),
        'status' => ImportRowStatus::Valid->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = ImportRunRow::find($id);

    expect($row->is_edited)->toBeBool()
        ->and($row->is_edited)->toBeFalse()
        ->and($row->extra_values)->toBeNull()
        ->and($row->resolved)->toBeNull()
        ->and($row->messages)->toBeNull()
        ->and($row->duplicate_of_id)->toBeNull();
});

it('exposes ImportRowStatus with the five documented cases', function () {
    expect(ImportRowStatus::Valid->value)->toBe('valid')
        ->and(ImportRowStatus::Warning->value)->toBe('warning')
        ->and(ImportRowStatus::Error->value)->toBe('error')
        ->and(ImportRowStatus::Duplicate->value)->toBe('duplicate')
        ->and(ImportRowStatus::Skipped->value)->toBe('skipped');
});
