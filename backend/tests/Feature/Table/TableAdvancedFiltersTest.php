<?php

use App\Models\BusinessFunction;
use App\Models\TableFilterView;
use App\Models\User;
use App\Models\UserTableFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\StubAdvancedFilterOverrideTableDefinition;
use Tests\Stubs\StubAdvancedFilterTableDefinition;

uses(RefreshDatabase::class);

/**
 * Create the standard business-functions.* permissions and return a
 * freshly-created user granted the requested subset — the stub domains reuse
 * the REAL BusinessFunction policy/permissions (see StubAdvancedFilter*
 * TableDefinition), exactly like the export engine's stub suite.
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('userWithBusinessFunctionAbilities')) {
    function userWithBusinessFunctionAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        return $user;
    }
}

beforeEach(function () {
    config(['tables.definitions' => [
        'stub-advanced-filters' => StubAdvancedFilterTableDefinition::class,
        'stub-advanced-filters-override' => StubAdvancedFilterOverrideTableDefinition::class,
    ]]);
});

it('exposes advancedFilters ordered + appliedAdvancedFilters, target/operator never leaked (AC-001)', function () {
    Sanctum::actingAs(userWithBusinessFunctionAbilities(['viewAny']));

    $data = $this->getJson('/api/tables/stub-advanced-filters/columns')->assertOk()->json('data');

    expect(array_column($data['advancedFilters'], 'name'))
        ->toBe(['name', 'id_range', 'created_range', 'name_in', 'is_unit', 'manager', 'users'])
        ->and($data['appliedAdvancedFilters'])->toBeNull();

    foreach ($data['advancedFilters'] as $descriptor) {
        expect($descriptor)->not->toHaveKey('target')->not->toHaveKey('operator');
    }
});

it('restricts rows by each advanced filter type in AND (AC-003)', function () {
    Sanctum::actingAs(userWithBusinessFunctionAbilities(['viewAny']));

    BusinessFunction::factory()->create(['name' => 'Sales Ops', 'is_business_unit' => false]);
    BusinessFunction::factory()->create(['name' => 'Sales HQ', 'is_business_unit' => false]);
    BusinessFunction::factory()->create(['name' => 'Support', 'is_business_unit' => false]);

    $response = $this->postJson('/api/tables/stub-advanced-filters/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'advancedFilters' => ['name' => 'Sales'],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Sales Ops', 'Sales HQ']); // default sort: id asc (creation order)
});

it('rejects an advancedFilters key outside the catalog allow-list with 422 (AC-004)', function () {
    Sanctum::actingAs(userWithBusinessFunctionAbilities(['viewAny']));

    $this->postJson('/api/tables/stub-advanced-filters/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'advancedFilters' => ['ghost' => 'x'],
    ])->assertUnprocessable()->assertJsonValidationErrors('advancedFilters.ghost');
});

it('rejects a mistyped advancedFilters value with 422 (AC-004)', function () {
    Sanctum::actingAs(userWithBusinessFunctionAbilities(['viewAny']));

    $this->postJson('/api/tables/stub-advanced-filters/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'advancedFilters' => ['id_range' => 'not-a-range'],
    ])->assertUnprocessable()->assertJsonValidationErrors('advancedFilters.id_range');
});

it('treats an injection payload as a literal bound value, never widening the result set (AC-005)', function () {
    Sanctum::actingAs(userWithBusinessFunctionAbilities(['viewAny']));

    BusinessFunction::factory()->create(['name' => 'Sales Ops', 'is_business_unit' => false]);

    $response = $this->postJson('/api/tables/stub-advanced-filters/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'advancedFilters' => ['name' => "' OR 1=1--"],
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(0);
});

it('applies a required filter\'s defaultValue when the request omits it (AC-006)', function () {
    Sanctum::actingAs(userWithBusinessFunctionAbilities(['viewAny']));

    BusinessFunction::factory()->create(['name' => 'A unit', 'is_business_unit' => true]);
    BusinessFunction::factory()->create(['name' => 'Not a unit', 'is_business_unit' => false]);

    // `is_unit` is required with defaultValue=false; omitted entirely here.
    $response = $this->postJson('/api/tables/stub-advanced-filters/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'advancedFilters' => ['name' => 'unit'],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Not a unit']); // matched "unit" AND defaulted is_business_unit=false
});

it('uses the domain override for a derived relational filter, not the direct-column path (AC-007)', function () {
    Sanctum::actingAs(userWithBusinessFunctionAbilities(['viewAny']));

    $jane = User::factory()->create(['name' => 'Jane Doe']);
    $john = User::factory()->create(['name' => 'John Roe']);
    BusinessFunction::factory()->withManager($jane)->create(['name' => 'Under Jane', 'is_business_unit' => false]);
    BusinessFunction::factory()->withManager($john)->create(['name' => 'Under John', 'is_business_unit' => false]);

    $response = $this->postJson('/api/tables/stub-advanced-filters-override/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'advancedFilters' => ['manager_name' => 'Jane'],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Under Jane']);
});

it('persists advancedFilters and restores them via appliedAdvancedFilters; reset clears both without touching filterModel shape (AC-008)', function () {
    $user = userWithBusinessFunctionAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $this->postJson('/api/tables/stub-advanced-filters/filters', [
        'filterModel' => [],
        'advancedFilters' => ['name' => 'Sales', 'is_unit' => true],
    ])->assertOk()->assertJsonPath('data.appliedAdvancedFilters.name', 'Sales');

    $stored = UserTableFilter::query()
        ->where('user_id', $user->id)->where('domain', 'stub-advanced-filters')->firstOrFail();

    expect($stored->advanced_filters)->toBe(['name' => 'Sales', 'is_unit' => true])
        ->and($stored->filters)->toBe([]); // existing column shape untouched

    expect($this->getJson('/api/tables/stub-advanced-filters/columns')->json('data.appliedAdvancedFilters'))
        ->toBe(['name' => 'Sales', 'is_unit' => true]);

    $this->deleteJson('/api/tables/stub-advanced-filters/filters')->assertNoContent();

    $this->assertDatabaseMissing('user_table_filters', ['user_id' => $user->id, 'domain' => 'stub-advanced-filters']);
    expect($this->getJson('/api/tables/stub-advanced-filters/columns')->json('data.appliedAdvancedFilters'))->toBeNull();
});

it('leaves persisted advancedFilters untouched when the save request omits the key', function () {
    $user = userWithBusinessFunctionAbilities(['viewAny']);
    Sanctum::actingAs($user);

    $this->postJson('/api/tables/stub-advanced-filters/filters', [
        'filterModel' => [],
        'advancedFilters' => ['name' => 'Sales'],
    ])->assertOk();

    // A later save touching ONLY filterModel must not clear the advanced filters.
    $this->postJson('/api/tables/stub-advanced-filters/filters', ['filterModel' => []])->assertOk();

    expect($this->getJson('/api/tables/stub-advanced-filters/columns')->json('data.appliedAdvancedFilters'))
        ->toBe(['name' => 'Sales']);
});

it('captures advancedFilters in a saved filter view and restores them; only the owner may edit (AC-009)', function () {
    $owner = userWithBusinessFunctionAbilities(['viewAny']);
    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/tables/stub-advanced-filters/filter-views', [
        'name' => 'My view',
        'filters' => [],
        'visibility' => 'private',
        'advancedFilters' => ['name' => 'Sales'],
    ])->assertCreated();

    $response->assertJsonPath('data.advanced_filters.name', 'Sales');

    $viewId = $response->json('data.id');
    $this->assertDatabaseHas('table_filter_views', ['id' => $viewId]);
    expect(TableFilterView::query()->findOrFail($viewId)->advanced_filters)->toBe(['name' => 'Sales']);

    $other = userWithBusinessFunctionAbilities(['viewAny']);
    Sanctum::actingAs($other);

    $this->putJson("/api/tables/stub-advanced-filters/filter-views/{$viewId}", [
        'name' => 'Hijacked',
        'filters' => [],
        'visibility' => 'private',
        'advancedFilters' => [],
    ])->assertForbidden();
});

it('returns 403 for GET columns and POST rows without business-functions.viewAny (AC-010)', function () {
    Sanctum::actingAs(userWithBusinessFunctionAbilities([]));

    $this->getJson('/api/tables/stub-advanced-filters/columns')->assertForbidden();
    $this->postJson('/api/tables/stub-advanced-filters/rows', [
        'startRow' => 0, 'endRow' => 50, 'advancedFilters' => ['name' => 'x'],
    ])->assertForbidden();
});

it('combines column filterModel, quick-search and advancedFilters in AND without conflicts (AC-019 regression)', function () {
    Sanctum::actingAs(userWithBusinessFunctionAbilities(['viewAny']));

    BusinessFunction::factory()->create(['name' => 'Sales Ops', 'is_business_unit' => false]);
    BusinessFunction::factory()->create(['name' => 'Sales Support', 'is_business_unit' => false]);
    BusinessFunction::factory()->create(['name' => 'Ops Team', 'is_business_unit' => false]);

    $response = $this->postJson('/api/tables/stub-advanced-filters/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'search' => 'sales',
        'advancedFilters' => ['name' => 'Ops'],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Sales Ops']); // "sales" (search) AND "Ops" (advanced) AND is_unit default(false)
});
