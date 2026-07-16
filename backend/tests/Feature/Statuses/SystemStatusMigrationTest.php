<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| AC-001 / AC-002 — system statuses + `group` schema migration
|--------------------------------------------------------------------------
|
| Not using RefreshDatabase: these tests drive real Artisan migrate calls and
| direct `require()`/down()/up() on the migration files to exercise the data
| migration against tables that already hold rows, mirroring
| LeadStatusMigrationTest's established pattern. Each test brings the schema
| back to a fully migrated, empty state via `migrate:fresh` before it exits.
*/

it('produces exactly the two system pipeline statuses on an empty database (AC-001)', function () {
    Artisan::call('migrate:fresh');

    $rows = DB::table('pipeline_statuses')->orderBy('sort_order')->get();

    expect($rows)->toHaveCount(2);

    expect($rows[0]->name)->toBe('Nuovo');
    expect($rows[0]->system_key)->toBe('new');
    expect($rows[0]->sort_order)->toBe(0);
    expect($rows[0]->color)->toBe('slate');
    expect($rows[0]->group)->toBe('open');

    expect($rows[1]->name)->toBe('Chiuso');
    expect($rows[1]->system_key)->toBe('closed');
    expect($rows[1]->sort_order)->toBe(10);
    expect($rows[1]->color)->toBe('green');
    expect($rows[1]->group)->toBe('closed');

    Artisan::call('migrate:fresh');
});

it('produces exactly the three system lead statuses on an empty database (AC-001, spec 0039 pivot)', function () {
    Artisan::call('migrate:fresh');

    $rows = DB::table('lead_statuses')->orderBy('sort_order')->get();

    expect($rows)->toHaveCount(3);

    expect($rows[0]->name)->toBe('Nuovo');
    expect($rows[0]->system_key)->toBe('new');
    expect($rows[0]->sort_order)->toBe(0);
    expect($rows[0]->group)->toBe('open');

    expect($rows[1]->name)->toBe('Chiuso con successo');
    expect($rows[1]->system_key)->toBe('won');
    expect($rows[1]->sort_order)->toBe(10);
    expect($rows[1]->color)->toBe('green');
    expect($rows[1]->group)->toBe('closed');

    expect($rows[2]->name)->toBe('Scartato');
    expect($rows[2]->system_key)->toBe('discarded');
    expect($rows[2]->sort_order)->toBe(20);
    expect($rows[2]->color)->toBe('red');
    expect($rows[2]->group)->toBe('closed');

    Artisan::call('migrate:fresh');
});

it('promotes a pre-existing "Nuovo" row without duplicating it and resequences custom pipeline statuses (AC-002)', function () {
    Artisan::call('migrate:fresh');

    // Roll back ONLY the pipeline_statuses add-columns migration directly
    // (not `migrate:rollback --step=1`, which would also unwind the
    // lead_statuses sibling migrated after it).
    $migration = require database_path('migrations/2026_07_16_130100_add_system_status_columns_to_pipeline_statuses_table.php');
    $migration->down();
    expect(Schema::hasColumn('pipeline_statuses', 'system_key'))->toBeFalse();

    // The rows created by the first migrate:fresh ("Nuovo"/"Chiuso") were NOT
    // deleted by down() — they remain as plain custom-looking rows, exactly
    // simulating pre-existing data that includes one named "Nuovo".
    expect(DB::table('pipeline_statuses')->where('name', 'Nuovo')->count())->toBe(1);
    expect(DB::table('pipeline_statuses')->where('name', 'Chiuso')->count())->toBe(1);

    // Deliberately out-of-order sort_order values to prove the resequence
    // follows the (sort_order, name, id) order, not insertion order.
    DB::table('pipeline_statuses')->insert([
        ['name' => 'Bozza', 'color' => 'slate', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'In corso', 'color' => 'green', 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Concluso', 'color' => 'teal', 'sort_order' => 1000, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration->up();

    expect(DB::table('pipeline_statuses')->where('name', 'Nuovo')->count())->toBe(1);
    expect(DB::table('pipeline_statuses')->where('name', 'Chiuso')->count())->toBe(1);

    $newRow = DB::table('pipeline_statuses')->where('name', 'Nuovo')->first();
    expect($newRow->system_key)->toBe('new');
    expect($newRow->sort_order)->toBe(0);
    expect($newRow->group)->toBe('open');

    $customs = DB::table('pipeline_statuses')
        ->whereNull('system_key')
        ->orderBy('sort_order')
        ->get(['name', 'sort_order']);
    expect($customs->pluck('name')->all())->toBe(['Bozza', 'In corso', 'Concluso']);
    expect($customs->pluck('sort_order')->all())->toBe([10, 20, 30]);

    $closedRow = DB::table('pipeline_statuses')->where('name', 'Chiuso')->first();
    expect($closedRow->system_key)->toBe('closed');
    expect($closedRow->sort_order)->toBe(40);
    expect($closedRow->group)->toBe('closed');

    Artisan::call('migrate:fresh');
});

it('promotes/renames a pre-existing "Chiuso" row to "Scartato" and resequences custom lead statuses (AC-002, spec 0039 pivot)', function () {
    Artisan::call('migrate:fresh');

    $migration = require database_path('migrations/2026_07_16_130200_add_system_status_columns_to_lead_statuses_table.php');
    $migration->down();
    expect(Schema::hasColumn('lead_statuses', 'system_key'))->toBeFalse();

    // The rows created by the first migrate:fresh ("Nuovo"/"Chiuso con
    // successo"/"Scartato") were NOT deleted by down() — they remain as
    // plain custom-looking rows. Reduce to a "Nuovo"/"Chiuso" pair (the
    // pre-pivot shape) to exercise the promote+rename path from scratch.
    DB::table('lead_statuses')->where('name', 'Chiuso con successo')->delete();
    DB::table('lead_statuses')->where('name', 'Scartato')->update(['name' => 'Chiuso']);

    expect(DB::table('lead_statuses')->where('name', 'Nuovo')->count())->toBe(1);
    expect(DB::table('lead_statuses')->where('name', 'Chiuso')->count())->toBe(1);

    DB::table('lead_statuses')->insert([
        ['name' => 'Contacted', 'color' => 'blue', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Qualified', 'color' => 'teal', 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration->up();

    expect(DB::table('lead_statuses')->where('name', 'Nuovo')->count())->toBe(1);
    expect(DB::table('lead_statuses')->where('name', 'Scartato')->count())->toBe(1);
    expect(DB::table('lead_statuses')->where('name', 'Chiuso')->count())->toBe(0);
    expect(DB::table('lead_statuses')->where('name', 'Chiuso con successo')->count())->toBe(1);

    $customs = DB::table('lead_statuses')
        ->whereNull('system_key')
        ->orderBy('sort_order')
        ->get(['name', 'sort_order']);
    expect($customs->pluck('name')->all())->toBe(['Contacted', 'Qualified']);
    expect($customs->pluck('sort_order')->all())->toBe([10, 20]);

    // "Scartato" (the renamed old "Chiuso") is ALWAYS last, after
    // "Chiuso con successo".
    $wonRow = DB::table('lead_statuses')->where('name', 'Chiuso con successo')->first();
    expect($wonRow->system_key)->toBe('won');
    expect($wonRow->sort_order)->toBe(30);
    expect($wonRow->group)->toBe('closed');

    $discardedRow = DB::table('lead_statuses')->where('name', 'Scartato')->first();
    expect($discardedRow->system_key)->toBe('discarded');
    expect($discardedRow->sort_order)->toBe(40);
    expect($discardedRow->group)->toBe('closed');

    Artisan::call('migrate:fresh');
});

it('prefers an already-existing "Scartato" row over renaming "Chiuso" (collision guard, AC-002)', function () {
    Artisan::call('migrate:fresh');

    $migration = require database_path('migrations/2026_07_16_130200_add_system_status_columns_to_lead_statuses_table.php');
    $migration->down();

    // Simulate pre-existing data with BOTH a "Chiuso" row AND an unrelated
    // custom row already named "Scartato" — renaming "Chiuso" into "Scartato"
    // would collide on the unique `name` constraint, so the existing
    // "Scartato" row must be promoted instead, "Chiuso" left as a plain
    // (now orphaned) custom row.
    DB::table('lead_statuses')->where('name', 'Chiuso con successo')->delete();
    DB::table('lead_statuses')->where('name', 'Scartato')->update(['name' => 'Chiuso']);
    DB::table('lead_statuses')->insert([
        'name' => 'Scartato', 'color' => 'orange', 'sort_order' => 50, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $migration->up();

    $discardedRow = DB::table('lead_statuses')->where('name', 'Scartato')->first();
    expect($discardedRow->system_key)->toBe('discarded');
    expect($discardedRow->color)->toBe('orange');

    // "Chiuso" is left as a plain custom row, not deleted or renamed again.
    $orphanedChiuso = DB::table('lead_statuses')->where('name', 'Chiuso')->first();
    expect($orphanedChiuso)->not->toBeNull();
    expect($orphanedChiuso->system_key)->toBeNull();

    Artisan::call('migrate:fresh');
});
