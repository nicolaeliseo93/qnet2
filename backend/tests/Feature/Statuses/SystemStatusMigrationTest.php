<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| AC-001 / AC-002 — system statuses + status groups schema migration
|--------------------------------------------------------------------------
|
| Not using RefreshDatabase: these tests drive real Artisan migrate calls and
| direct `require()`/down()/up() on the migration files to exercise the data
| migration against tables that already hold rows, mirroring
| LeadStatusMigrationTest's established pattern. Each test brings the schema
| back to a fully migrated, empty state via `migrate:fresh` before it exits.
*/

it('produces exactly the two system statuses and two groups on an empty database (AC-001)', function () {
    Artisan::call('migrate:fresh');

    foreach (['pipeline_statuses', 'lead_statuses'] as $table) {
        $rows = DB::table($table)->orderBy('sort_order')->get();

        expect($rows)->toHaveCount(2);

        expect($rows[0]->name)->toBe('Nuovo');
        expect($rows[0]->system_key)->toBe('new');
        expect($rows[0]->sort_order)->toBe(0);
        expect($rows[0]->color)->toBe('slate');

        expect($rows[1]->name)->toBe('Chiuso');
        expect($rows[1]->system_key)->toBe('closed');
        expect($rows[1]->sort_order)->toBe(10);
        expect($rows[1]->color)->toBe('green');
    }

    $groups = DB::table('status_groups')->orderBy('sort_order')->get();
    expect($groups)->toHaveCount(2);
    expect($groups[0]->name)->toBe('Aperto');
    expect($groups[0]->color)->toBe('blue');
    expect($groups[1]->name)->toBe('Chiuso');
    expect($groups[1]->color)->toBe('green');

    $newRow = DB::table('pipeline_statuses')->where('system_key', 'new')->first();
    expect($newRow->status_group_id)->toBe($groups[0]->id);
    $closedRow = DB::table('pipeline_statuses')->where('system_key', 'closed')->first();
    expect($closedRow->status_group_id)->toBe($groups[1]->id);

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

    $groups = DB::table('status_groups')->orderBy('sort_order')->get();
    expect($groups)->toHaveCount(2);

    $newRow = DB::table('pipeline_statuses')->where('name', 'Nuovo')->first();
    expect($newRow->system_key)->toBe('new');
    expect($newRow->sort_order)->toBe(0);
    expect($newRow->status_group_id)->toBe($groups[0]->id);

    $customs = DB::table('pipeline_statuses')
        ->whereNull('system_key')
        ->orderBy('sort_order')
        ->get(['name', 'sort_order']);
    expect($customs->pluck('name')->all())->toBe(['Bozza', 'In corso', 'Concluso']);
    expect($customs->pluck('sort_order')->all())->toBe([10, 20, 30]);

    $closedRow = DB::table('pipeline_statuses')->where('name', 'Chiuso')->first();
    expect($closedRow->system_key)->toBe('closed');
    expect($closedRow->sort_order)->toBe(40);
    expect($closedRow->status_group_id)->toBe($groups[1]->id);

    Artisan::call('migrate:fresh');
});

it('promotes a pre-existing "Nuovo" row and resequences custom lead statuses (AC-002)', function () {
    Artisan::call('migrate:fresh');

    $migration = require database_path('migrations/2026_07_16_130200_add_system_status_columns_to_lead_statuses_table.php');
    $migration->down();
    expect(Schema::hasColumn('lead_statuses', 'system_key'))->toBeFalse();

    expect(DB::table('lead_statuses')->where('name', 'Nuovo')->count())->toBe(1);
    expect(DB::table('lead_statuses')->where('name', 'Chiuso')->count())->toBe(1);

    DB::table('lead_statuses')->insert([
        ['name' => 'Contacted', 'color' => 'blue', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Qualified', 'color' => 'teal', 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $migration->up();

    expect(DB::table('lead_statuses')->where('name', 'Nuovo')->count())->toBe(1);
    expect(DB::table('lead_statuses')->where('name', 'Chiuso')->count())->toBe(1);

    $customs = DB::table('lead_statuses')
        ->whereNull('system_key')
        ->orderBy('sort_order')
        ->get(['name', 'sort_order']);
    expect($customs->pluck('name')->all())->toBe(['Contacted', 'Qualified']);
    expect($customs->pluck('sort_order')->all())->toBe([10, 20]);

    $closedRow = DB::table('lead_statuses')->where('name', 'Chiuso')->first();
    expect($closedRow->system_key)->toBe('closed');
    expect($closedRow->sort_order)->toBe(30);

    Artisan::call('migrate:fresh');
});
