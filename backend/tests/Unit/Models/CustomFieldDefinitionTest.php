<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

it('creates the custom_field_definitions table with the expected columns', function () {
    expect(Schema::hasTable('custom_field_definitions'))->toBeTrue();
    expect(Schema::hasColumns('custom_field_definitions', [
        'id', 'entity_type', 'key', 'type', 'label', 'description', 'help_text',
        'placeholder', 'icon', 'group', 'tab', 'sort_order', 'default_value',
        'config', 'validation', 'relation_target', 'is_indexed', 'is_active',
        'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('entity_type/key/type/label are required at the database level', function () {
    expect(fn () => DB::table('custom_field_definitions')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('key is unique per entity_type at the database level', function () {
    CustomFieldDefinition::factory()->create(['entity_type' => 'companies', 'key' => 'dup']);

    expect(fn () => CustomFieldDefinition::factory()->create(['entity_type' => 'companies', 'key' => 'dup']))
        ->toThrow(QueryException::class);
});

it('allows the same key across different entity_type', function () {
    CustomFieldDefinition::factory()->create(['entity_type' => 'companies', 'key' => 'shared']);

    $second = CustomFieldDefinition::factory()->create(['entity_type' => 'referents', 'key' => 'shared']);

    expect($second->exists)->toBeTrue();
});

it('down() reverses the custom_field_definitions migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_08_130000_create_custom_field_definitions_table.php');

    Schema::dropIfExists('custom_field_options');
    $migration->down();

    expect(Schema::hasTable('custom_field_definitions'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('custom_field_definitions'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model cast, relation, activity log, morph map
// ---------------------------------------------------------------------------

it('casts json columns to array and flags to boolean', function () {
    $definition = CustomFieldDefinition::factory()->create([
        'default_value' => ['x' => 1],
        'config' => ['minLength' => 2],
        'validation' => ['required' => true],
        'relation_target' => ['entity_type' => 'companies', 'cardinality' => 'one'],
        'is_indexed' => true,
        'is_active' => false,
    ]);

    $fresh = $definition->fresh();

    expect($fresh->default_value)->toBe(['x' => 1]);
    expect($fresh->config)->toBe(['minLength' => 2]);
    expect($fresh->validation)->toBe(['required' => true]);
    expect($fresh->relation_target)->toBe(['entity_type' => 'companies', 'cardinality' => 'one']);
    expect($fresh->is_indexed)->toBeTrue();
    expect($fresh->is_active)->toBeFalse();
});

it('options() is a HasMany relation to CustomFieldOption ordered by sort_order', function () {
    $definition = CustomFieldDefinition::factory()->ofType('enum')->create();
    CustomFieldOption::factory()->create(['definition_id' => $definition->id, 'value' => 'b', 'sort_order' => 2]);
    CustomFieldOption::factory()->create(['definition_id' => $definition->id, 'value' => 'a', 'sort_order' => 1]);

    $relation = $definition->options();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(CustomFieldOption::class);
    expect($definition->options()->pluck('value')->all())->toBe(['a', 'b']);
});

it('logs model activity', function () {
    expect(class_uses(CustomFieldDefinition::class))->toHaveKey(LogsModelActivity::class);
});

it('registers the "custom_field" morph alias', function () {
    expect(array_search(CustomFieldDefinition::class, Relation::morphMap(), true))->toBe('custom_field');
});
