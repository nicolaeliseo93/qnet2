<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| AC-001 / AC-002 — system statuses + `group` schema migration
|--------------------------------------------------------------------------
|
| Not using RefreshDatabase: these tests drive real Artisan migrate calls and
| direct `require()`/down()/up() on the migration files to exercise the data
| migration against tables that already hold rows. Each test brings the schema
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
