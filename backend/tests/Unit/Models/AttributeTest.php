<?php

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

it('creates the attributes and attribute_options tables with the expected columns', function () {
    expect(Schema::hasTable('attributes'))->toBeTrue();
    expect(Schema::hasColumns('attributes', [
        'id', 'code', 'name', 'type', 'description', 'help_text', 'placeholder',
        'icon', 'config', 'relation_target', 'created_at', 'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('attribute_options'))->toBeTrue();
    expect(Schema::hasColumns('attribute_options', [
        'id', 'attribute_id', 'value', 'label', 'color', 'icon', 'sort_order', 'is_default',
    ]))->toBeTrue();
});

it('code/name/type are required at the database level', function () {
    expect(fn () => DB::table('attributes')->insert(['name' => 'x', 'type' => 'text', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('attribute code is unique at the database level', function () {
    Attribute::factory()->create(['code' => 'dup']);

    expect(fn () => Attribute::factory()->create(['code' => 'dup']))->toThrow(QueryException::class);
});

it('deleting an attribute cascades to its options', function () {
    $attribute = Attribute::factory()->enum(2)->create();
    $optionIds = $attribute->options->pluck('id');

    $attribute->delete();

    expect(AttributeOption::whereIn('id', $optionIds)->count())->toBe(0);
});

it('down() reverses the original attributes migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_07_110000_create_attributes_table.php');

    Schema::dropIfExists('attribute_category');
    Schema::dropIfExists('attribute_options');
    $migration->down();

    expect(Schema::hasTable('attributes'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('attributes'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model relations, cast, activity log, morph map
// ---------------------------------------------------------------------------

it('stores `type` as a plain string (no cast — App\CustomFields\FieldTypeRegistry is the allow-list)', function () {
    $attribute = Attribute::factory()->create(['type' => 'integer']);

    expect($attribute->fresh()->type)->toBe('integer');
});

it('casts config/relation_target to array', function () {
    $attribute = Attribute::factory()->create([
        'type' => 'relation',
        'config' => ['min' => 1],
        'relation_target' => ['entity_type' => 'referents', 'cardinality' => 'one', 'for_select_resource' => 'referents'],
    ]);

    expect($attribute->fresh()->config)->toBe(['min' => 1]);
    expect($attribute->fresh()->relation_target)->toBe(['entity_type' => 'referents', 'cardinality' => 'one', 'for_select_resource' => 'referents']);
});

it('options() is a HasMany relation to AttributeOption', function () {
    $relation = (new Attribute)->options();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(AttributeOption::class);
});

it('categories() is a BelongsToMany relation with is_required/sort_order pivot', function () {
    $relation = (new Attribute)->categories();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
    expect($relation->getPivotColumns())->toContain('is_required', 'sort_order');
});

it('logs model activity', function () {
    expect(class_uses(Attribute::class))->toHaveKey(LogsModelActivity::class);
});

it('registers the "attribute" morph alias', function () {
    expect(array_search(Attribute::class, Relation::morphMap(), true))->toBe('attribute');
});
