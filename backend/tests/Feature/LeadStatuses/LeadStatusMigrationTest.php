<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| AC-012 / BR-1 — schema migration + mandatory-fk backfill
|--------------------------------------------------------------------------
|
| Not using RefreshDatabase: these tests drive real Artisan migrate/rollback
| calls to exercise the backfill migration's up() against a leads table that
| already holds data, which RefreshDatabase's per-test transaction wrapping
| would get in the way of. Each test brings the schema back to a fully
| migrated, empty state via `migrate:fresh` before it exits, so it leaves no
| trace for the rest of the suite.
*/

it('migrate:fresh runs clean on an empty database (AC-012)', function () {
    Artisan::call('migrate:fresh');

    expect(Schema::hasTable('lead_statuses'))->toBeTrue();
    expect(Schema::hasColumn('leads', 'lead_status_id'))->toBeTrue();
    // requirement changed (spec 0039 pivot, D-2): the system-status migration
    // now seeds the 3 mandatory rows ("Nuovo"/"Chiuso con successo"/
    // "Scartato") unconditionally, even on an empty database — the baseline
    // is 3, not 0.
    expect(DB::table('lead_statuses')->count())->toBe(3);

    Artisan::call('migrate:fresh');
});

it('backfills lead_status_id for pre-existing leads and locks the column NOT NULL (AC-012, BR-1)', function () {
    Artisan::call('migrate:fresh');

    // Roll back ONLY the backfill migration directly (not via `migrate:rollback
    // --step=1`, which counts the single most-recently-RUN migration file
    // chronologically — spec 0033 added 3 more `leads`/`import_runs`
    // migrations after this one, so that would now roll back the wrong file),
    // simulating the leads table as it existed before this feature: no
    // lead_status_id column at all. Mirrors LeadTest's direct migration
    // require()/down()/up() pattern.
    $migration = require database_path('migrations/2026_07_14_160100_add_lead_status_id_to_leads_table.php');
    $migration->down();
    expect(Schema::hasColumn('leads', 'lead_status_id'))->toBeFalse();

    $registryId = DB::table('registries')->insertGetId(['name' => 'Backfill Registry']);
    $campaignId = DB::table('campaigns')->insertGetId(['code' => 'CMP-BF01', 'name' => 'Backfill Campaign']);
    $leadId = DB::table('leads')->insertGetId([
        'registry_id' => $registryId,
        'campaign_id' => $campaignId,
    ]);

    $migration->up();

    expect(Schema::hasColumn('leads', 'lead_status_id'))->toBeTrue();

    $defaultStatus = DB::table('lead_statuses')->where('name', 'New')->first();
    expect($defaultStatus)->not->toBeNull();
    expect($defaultStatus->color)->toBe('slate');

    $lead = DB::table('leads')->find($leadId);
    expect($lead->lead_status_id)->toBe($defaultStatus->id);

    // The column is now NOT NULL: an insert that omits it must fail, not
    // silently store a null FK.
    $insertWithoutStatus = fn () => DB::table('leads')->insert([
        'registry_id' => $registryId,
        'campaign_id' => $campaignId,
    ]);
    expect($insertWithoutStatus)->toThrow(QueryException::class);

    Artisan::call('migrate:fresh');
});
