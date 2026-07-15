<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Stubs\StubAdvancedFilterOverrideTableDefinition;
use Tests\Stubs\StubAdvancedFilterTableDefinition;
use Tests\Stubs\StubExportTableDefinition;
use Tests\TestCase;

// Touches the database, so bind the full TestCase + RefreshDatabase
// explicitly (Unit suite has no default RefreshDatabase binding).
uses(TestCase::class, RefreshDatabase::class);

it('defaults advancedFilters() to an empty array (AC-002 baseline)', function () {
    $definition = new StubExportTableDefinition;

    expect($definition->advancedFilters())->toBe([])
        ->and($definition->advancedFilterableIds())->toBe([]);
});

it('derives advancedFilterableIds() from the catalog names', function () {
    $definition = new StubAdvancedFilterTableDefinition;

    expect($definition->advancedFilterableIds())->toBe([
        'name', 'id_range', 'created_range', 'name_in', 'is_unit', 'manager', 'users',
    ]);
});

it('resolveConfig() emits advancedFilters ordered by `order`, stripped of `target`/`operator` (AC-001)', function () {
    $definition = new StubAdvancedFilterTableDefinition;
    $actor = User::factory()->create();

    $config = $definition->resolveConfig($actor);

    $names = array_column($config['advancedFilters'], 'name');
    expect($names)->toBe(['name', 'id_range', 'created_range', 'name_in', 'is_unit', 'manager', 'users']);

    foreach ($config['advancedFilters'] as $descriptor) {
        expect($descriptor)->not->toHaveKey('target')->not->toHaveKey('operator');
    }

    // Placeholder overwritten later by TableFilterStateService::applyTo().
    expect($config['appliedAdvancedFilters'])->toBeNull();
});

it('default applyAdvancedFilter() delegates a direct-column type to AdvancedFilterApplier', function () {
    BusinessFunction::factory()->create(['name' => 'Sales Ops']);
    BusinessFunction::factory()->create(['name' => 'Support']);

    $definition = new StubAdvancedFilterTableDefinition;
    $query = $definition->baseQuery();

    $catalog = array_column($definition->advancedFilters(), null, 'name');
    $handled = $definition->applyAdvancedFilter($query, 'name', $catalog['name'], 'Sales');

    expect($handled)->toBeTrue()
        ->and($query->pluck('name')->all())->toBe(['Sales Ops']);
});

it('default applyAdvancedFilter() delegates a `relation` type generically via whereHas (no domain code)', function () {
    $manager = User::factory()->create();
    BusinessFunction::factory()->withManager($manager)->create(['name' => 'Under manager']);
    BusinessFunction::factory()->create(['name' => 'No manager']);

    $definition = new StubAdvancedFilterTableDefinition;
    $query = $definition->baseQuery();

    $catalog = array_column($definition->advancedFilters(), null, 'name');
    $definition->applyAdvancedFilter($query, 'manager', $catalog['manager'], $manager->id);

    expect($query->pluck('name')->all())->toBe(['Under manager']);
});

it('a domain override handles its own derived filter, then falls back to parent for everything else (AC-007)', function () {
    $manager = User::factory()->create(['name' => 'Jane Doe']);
    $other = User::factory()->create(['name' => 'John Roe']);
    BusinessFunction::factory()->withManager($manager)->create(['name' => 'Under Jane']);
    BusinessFunction::factory()->withManager($other)->create(['name' => 'Under John']);

    $definition = new StubAdvancedFilterOverrideTableDefinition;

    // The derived filter: searches the related manager BY NAME (not id) —
    // impossible via the generic relation-by-id path, requires the override.
    $query = $definition->baseQuery();
    $catalog = array_column($definition->advancedFilters(), null, 'name');
    $handled = $definition->applyAdvancedFilter($query, 'manager_name', $catalog['manager_name'], 'Jane');

    expect($handled)->toBeTrue()
        ->and($query->pluck('name')->all())->toBe(['Under Jane']);

    // A direct-column filter NOT special-cased by the override still resolves
    // via parent::applyAdvancedFilter() (the generic default).
    $fallbackQuery = $definition->baseQuery();
    $definition->applyAdvancedFilter($fallbackQuery, 'name', $catalog['name'], 'Under John');

    expect($fallbackQuery->pluck('name')->all())->toBe(['Under John']);
});
