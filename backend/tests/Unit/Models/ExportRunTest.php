<?php

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\ExportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly, mirroring ImportRunTest (the default Pest
// binding only applies to the Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 — schema + casts + relation
// ---------------------------------------------------------------------------

it('creates the export_runs table with the expected columns', function () {
    expect(Schema::hasTable('export_runs'))->toBeTrue();
    expect(Schema::hasColumns('export_runs', [
        'id', 'resource', 'user_id', 'status', 'format', 'original_filename',
        'state', 'file_path', 'row_count', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_03_160000_create_export_runs_table.php');

    $migration->down();
    expect(Schema::hasTable('export_runs'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('export_runs'))->toBeTrue();
});

it('casts status/format to their enums and state to array', function () {
    $run = ExportRun::factory()->create([
        'status' => ExportStatus::Completed,
        'format' => ExportFormat::Xlsx,
        'state' => ['columns' => [['colId' => 'name', 'header' => 'Name']]],
    ]);

    $fresh = $run->fresh();

    expect($fresh->status)->toBeInstanceOf(ExportStatus::class)
        ->and($fresh->status)->toBe(ExportStatus::Completed)
        ->and($fresh->format)->toBeInstanceOf(ExportFormat::class)
        ->and($fresh->format)->toBe(ExportFormat::Xlsx)
        ->and($fresh->state)->toBeArray()
        ->and($fresh->state['columns'][0]['colId'])->toBe('name');
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $run = ExportRun::factory()->create(['user_id' => $user->id]);

    expect($run->user)->toBeInstanceOf(User::class)
        ->and($run->user->id)->toBe($user->id);
});

it('file_path and row_count are nullable at the database level', function () {
    $user = User::factory()->create();

    $id = DB::table('export_runs')->insertGetId([
        'resource' => 'stub-exports',
        'user_id' => $user->id,
        'status' => ExportStatus::Processing->value,
        'format' => ExportFormat::Csv->value,
        'original_filename' => 'stub-exports-2026-07-03.csv',
        'state' => json_encode(['columns' => []]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('export_runs')->find($id);

    expect($row->file_path)->toBeNull()
        ->and($row->row_count)->toBeNull();
});

it('user_id cascades on delete', function () {
    $user = User::factory()->create();
    $run = ExportRun::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(ExportRun::find($run->id))->toBeNull();
});
