<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 — schema + casts + relation
// ---------------------------------------------------------------------------

it('creates the import_runs table with the expected columns', function () {
    expect(Schema::hasTable('import_runs'))->toBeTrue();
    expect(Schema::hasColumns('import_runs', [
        'id', 'resource', 'user_id', 'status', 'original_filename', 'stored_path',
        'total_rows', 'valid_rows', 'invalid_rows', 'imported_rows',
        'error_report_path', 'preview', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_03_150000_create_import_runs_table.php');

    $migration->down();
    expect(Schema::hasTable('import_runs'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('import_runs'))->toBeTrue();
});

it('casts status to ImportStatus and preview to array', function () {
    $run = ImportRun::factory()->create([
        'status' => ImportStatus::AwaitingConfirmation,
        'preview' => ['columns' => ['name'], 'valid_sample' => [], 'invalid_sample' => []],
    ]);

    $fresh = $run->fresh();

    expect($fresh->status)->toBeInstanceOf(ImportStatus::class)
        ->and($fresh->status)->toBe(ImportStatus::AwaitingConfirmation)
        ->and($fresh->preview)->toBeArray()
        ->and($fresh->preview['columns'])->toBe(['name']);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $user->id]);

    expect($run->user)->toBeInstanceOf(User::class)
        ->and($run->user->id)->toBe($user->id);
});

it('total/valid/invalid rows default to 0 at the database level, imported_rows is nullable', function () {
    $user = User::factory()->create();

    $id = DB::table('import_runs')->insertGetId([
        'resource' => 'stub-widgets',
        'user_id' => $user->id,
        'status' => ImportStatus::Validating->value,
        'original_filename' => 'rows.csv',
        'stored_path' => 'imports/rows.csv',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('import_runs')->find($id);

    expect((int) $row->total_rows)->toBe(0)
        ->and((int) $row->valid_rows)->toBe(0)
        ->and((int) $row->invalid_rows)->toBe(0)
        ->and($row->imported_rows)->toBeNull()
        ->and($row->error_report_path)->toBeNull()
        ->and($row->preview)->toBeNull();
});

it('user_id cascades on delete', function () {
    $user = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(ImportRun::find($run->id))->toBeNull();
});
