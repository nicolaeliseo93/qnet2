<?php

use App\Models\BusinessFunction;
use App\Services\Table\TableQueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Stubs\StubExportTableDefinition;
use Tests\TestCase;

// Touches the database, so bind the full TestCase + RefreshDatabase
// explicitly (Unit suite has no default RefreshDatabase binding).
uses(TestCase::class, RefreshDatabase::class);

it('applies filterModel + search + sortModel to the definition baseQuery (AC-009 regression surface)', function () {
    BusinessFunction::factory()->create(['name' => 'Sales Unit', 'is_business_unit' => true]);
    BusinessFunction::factory()->create(['name' => 'Support', 'is_business_unit' => false]);
    BusinessFunction::factory()->create(['name' => 'Sales Ops', 'is_business_unit' => false]);

    $definition = new StubExportTableDefinition;
    $builder = app(TableQueryBuilder::class);

    $query = $builder->build($definition, [
        'filterModel' => ['is_business_unit' => ['values' => [false]]],
        'search' => 'sales',
        'sortModel' => [['colId' => 'name', 'sort' => 'desc']],
    ]);

    $names = $query->pluck('name')->all();

    expect($names)->toBe(['Sales Ops']); // matches "sales" AND is_business_unit=false
});

it('falls back to the definition defaultSort when no sortModel is given', function () {
    BusinessFunction::factory()->create(['name' => 'Beta']);
    BusinessFunction::factory()->create(['name' => 'Alpha']);

    $definition = new StubExportTableDefinition; // defaultSort: id asc
    $builder = app(TableQueryBuilder::class);

    $query = $builder->build($definition, []);

    $names = $query->pluck('name')->all();

    expect($names)->toBe(['Beta', 'Alpha']); // creation (id) order, not alphabetical
});

it('ignores a filterModel key outside the whitelist (defence in depth)', function () {
    BusinessFunction::factory()->create(['name' => 'Sales']);

    $definition = new StubExportTableDefinition; // `tags` is NOT filterable
    $builder = app(TableQueryBuilder::class);

    $query = $builder->build($definition, [
        'filterModel' => ['tags' => ['filter' => 'anything']],
    ]);

    expect($query->count())->toBe(1); // the bogus filter key was silently ignored
});

it('ignores a sortModel colId outside the sortable whitelist', function () {
    BusinessFunction::factory()->create(['name' => 'Beta']);
    BusinessFunction::factory()->create(['name' => 'Alpha']);

    $definition = new StubExportTableDefinition;
    $builder = app(TableQueryBuilder::class);

    // `tags` is not sortable: falls back to the definition's defaultSort (id asc).
    $query = $builder->build($definition, [
        'sortModel' => [['colId' => 'tags', 'sort' => 'asc']],
    ]);

    expect($query->pluck('name')->all())->toBe(['Beta', 'Alpha']);
});
