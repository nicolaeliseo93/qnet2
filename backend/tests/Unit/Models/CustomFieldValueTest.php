<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 — schema
// ---------------------------------------------------------------------------

it('creates the custom_field_values table with the expected columns', function () {
    expect(Schema::hasTable('custom_field_values'))->toBeTrue();
    expect(Schema::hasColumns('custom_field_values', [
        'id', 'entity_type', 'entity_id', 'values', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('entity_type/entity_id/values are required at the database level', function () {
    expect(fn () => DB::table('custom_field_values')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('is unique per (entity_type, entity_id) at the database level', function () {
    CustomFieldValue::factory()->create(['entity_type' => 'companies', 'entity_id' => 1, 'values' => []]);

    expect(fn () => CustomFieldValue::factory()->create(['entity_type' => 'companies', 'entity_id' => 1, 'values' => []]))
        ->toThrow(QueryException::class);
});

it('allows the same entity_id across different entity_type', function () {
    CustomFieldValue::factory()->create(['entity_type' => 'companies', 'entity_id' => 1, 'values' => []]);

    $second = CustomFieldValue::factory()->create(['entity_type' => 'referents', 'entity_id' => 1, 'values' => []]);

    expect($second->exists)->toBeTrue();
});

it('down() reverses the custom_field_values migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_08_130200_create_custom_field_values_table.php');

    $migration->down();

    expect(Schema::hasTable('custom_field_values'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('custom_field_values'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model cast, NO activity log, NO morph alias
// ---------------------------------------------------------------------------

it('casts values to array and entity_id to integer', function () {
    $value = CustomFieldValue::factory()->create(['entity_id' => '42', 'values' => ['priority' => 'high']]);

    $fresh = $value->fresh();

    expect($fresh->entity_id)->toBeInt()->toBe(42);
    expect($fresh->values)->toBe(['priority' => 'high']);
});

it('does not log model activity (high write volume, one row per save)', function () {
    expect(class_uses(CustomFieldValue::class))->not->toHaveKey(LogsModelActivity::class);
});

it('does not register a morph alias', function () {
    expect(array_search(CustomFieldValue::class, Relation::morphMap(), true))->toBeFalse();
});
