<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| AC-001 / BR-1/BR-2 — schema migration + mandatory-fk backfill
|--------------------------------------------------------------------------
|
| Not using RefreshDatabase: these tests drive real Artisan migrate/rollback
| calls to exercise the backfill migration's up() against an opportunities
| table that already holds data, which RefreshDatabase's per-test
| transaction wrapping would get in the way of. Each test brings the schema
| back to a fully migrated, empty state via `migrate:fresh` before it exits,
| so it leaves no trace for the rest of the suite.
*/

it('migrate:fresh runs clean on an empty database (AC-001)', function () {
    Artisan::call('migrate:fresh');

    expect(Schema::hasTable('opportunity_statuses'))->toBeTrue();
    expect(Schema::hasColumn('opportunities', 'opportunity_status_id'))->toBeTrue();
    // spec 0043, D-1/D-2: the create migration seeds the 3 mandatory rows
    // ("Nuova"/"Chiusa con successo"/"Persa") unconditionally, even on an
    // empty database.
    expect(DB::table('opportunity_statuses')->count())->toBe(3);

    $rows = DB::table('opportunity_statuses')->orderBy('sort_order')->get(['name', 'system_key', 'group', 'sort_order']);
    expect($rows->pluck('system_key')->all())->toBe(['new', 'won', 'lost']);
    expect($rows->firstWhere('system_key', 'new')->sort_order)->toBe(0);
    expect($rows->firstWhere('system_key', 'new')->group)->toBe('open');
    expect($rows->firstWhere('system_key', 'lost')->group)->toBe('closed');

    Artisan::call('migrate:fresh');
});

it('backfills opportunity_status_id for pre-existing opportunities and locks the column NOT NULL (BR-2)', function () {
    Artisan::call('migrate:fresh');

    // Roll back ONLY the backfill migration directly, simulating the
    // opportunities table as it existed before this feature: no
    // opportunity_status_id column at all. Mirrors LeadStatusMigrationTest's
    // direct migration require()/down()/up() pattern.
    $migration = require database_path('migrations/2026_07_17_200001_add_opportunity_status_id_to_opportunities_table.php');
    $migration->down();
    expect(Schema::hasColumn('opportunities', 'opportunity_status_id'))->toBeFalse();

    $registryId = DB::table('registries')->insertGetId(['name' => 'Backfill Registry']);
    $opportunityId = DB::table('opportunities')->insertGetId([
        'name' => 'Backfill Deal',
        'registry_id' => $registryId,
    ]);

    $migration->up();

    expect(Schema::hasColumn('opportunities', 'opportunity_status_id'))->toBeTrue();

    $defaultStatus = DB::table('opportunity_statuses')->where('system_key', 'new')->first();
    expect($defaultStatus)->not->toBeNull();

    $opportunity = DB::table('opportunities')->find($opportunityId);
    expect($opportunity->opportunity_status_id)->toBe($defaultStatus->id);

    // The column is now NOT NULL: an insert that omits it must fail, not
    // silently store a null FK.
    $insertWithoutStatus = fn () => DB::table('opportunities')->insert([
        'name' => 'No status',
        'registry_id' => $registryId,
    ]);
    expect($insertWithoutStatus)->toThrow(QueryException::class);

    Artisan::call('migrate:fresh');
});
