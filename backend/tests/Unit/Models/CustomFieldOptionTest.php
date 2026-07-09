<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

it('creates the custom_field_options table with the expected columns', function () {
    expect(Schema::hasTable('custom_field_options'))->toBeTrue();
    expect(Schema::hasColumns('custom_field_options', [
        'id', 'definition_id', 'value', 'label', 'color', 'icon', 'sort_order',
        'is_default', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('definition_id/value/label are required at the database level', function () {
    expect(fn () => DB::table('custom_field_options')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('value is unique per definition_id at the database level', function () {
    $definition = CustomFieldDefinition::factory()->ofType('enum')->create();
    CustomFieldOption::factory()->create(['definition_id' => $definition->id, 'value' => 'dup']);

    expect(fn () => CustomFieldOption::factory()->create(['definition_id' => $definition->id, 'value' => 'dup']))
        ->toThrow(QueryException::class);
});

it('deleting a definition cascades to its options', function () {
    $definition = CustomFieldDefinition::factory()->ofType('enum')->create();
    $option = CustomFieldOption::factory()->create(['definition_id' => $definition->id]);

    $definition->delete();

    expect(CustomFieldOption::find($option->id))->toBeNull();
});

it('down() reverses the custom_field_options migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_08_130100_create_custom_field_options_table.php');

    $migration->down();

    expect(Schema::hasTable('custom_field_options'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('custom_field_options'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model cast, relation, activity log, morph map
// ---------------------------------------------------------------------------

it('casts is_default to boolean and sort_order to integer', function () {
    $option = CustomFieldOption::factory()->default()->create(['sort_order' => '3']);

    $fresh = $option->fresh();

    expect($fresh->is_default)->toBeTrue();
    expect($fresh->sort_order)->toBeInt()->toBe(3);
});

it('definition() is a BelongsTo relation to CustomFieldDefinition', function () {
    $relation = (new CustomFieldOption)->definition();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(CustomFieldDefinition::class);
});

it('logs model activity', function () {
    expect(class_uses(CustomFieldOption::class))->toHaveKey(LogsModelActivity::class);
});

it('registers the "custom_field_option" morph alias', function () {
    expect(array_search(CustomFieldOption::class, Relation::morphMap(), true))->toBe('custom_field_option');
});
