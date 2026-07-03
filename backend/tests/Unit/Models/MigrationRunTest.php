<?php

use App\Enums\MigrationStatus;
use App\Models\MigrationRun;
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
// AC-003 — schema + casts + relation
// ---------------------------------------------------------------------------

it('creates the migration_runs table with the expected columns', function () {
    expect(Schema::hasTable('migration_runs'))->toBeTrue();
    expect(Schema::hasColumns('migration_runs', [
        'id', 'source', 'user_id', 'status',
        'total_rows', 'created_rows', 'skipped_rows', 'failed_rows',
        'report', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_04_100500_create_migration_runs_table.php');

    $migration->down();
    expect(Schema::hasTable('migration_runs'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('migration_runs'))->toBeTrue();
});

it('casts status to MigrationStatus and report to array', function () {
    $run = MigrationRun::factory()->create([
        'status' => MigrationStatus::Processing,
        'report' => [['old_id' => 7, 'level' => 'warning', 'message' => 'Unresolved role reference.']],
    ]);

    $fresh = $run->fresh();

    expect($fresh->status)->toBeInstanceOf(MigrationStatus::class)
        ->and($fresh->status)->toBe(MigrationStatus::Processing)
        ->and($fresh->report)->toBeArray()
        ->and($fresh->report[0]['message'])->toBe('Unresolved role reference.');
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $run = MigrationRun::factory()->create(['user_id' => $user->id]);

    expect($run->user)->toBeInstanceOf(User::class)
        ->and($run->user->id)->toBe($user->id);
});

it('total/created/skipped/failed rows default to 0 at the database level, report is nullable', function () {
    $user = User::factory()->create();

    $id = DB::table('migration_runs')->insertGetId([
        'source' => 'users',
        'user_id' => $user->id,
        'status' => MigrationStatus::Pending->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $row = DB::table('migration_runs')->find($id);

    expect((int) $row->total_rows)->toBe(0)
        ->and((int) $row->created_rows)->toBe(0)
        ->and((int) $row->skipped_rows)->toBe(0)
        ->and((int) $row->failed_rows)->toBe(0)
        ->and($row->report)->toBeNull();
});

it('user_id cascades on delete', function () {
    $user = User::factory()->create();
    $run = MigrationRun::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(MigrationRun::find($run->id))->toBeNull();
});
