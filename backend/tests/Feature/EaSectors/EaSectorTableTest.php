<?php

use App\Models\EaSector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('eaSectorUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function eaSectorUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("ea-sectors.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("ea-sectors.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-012 — columns config
// ---------------------------------------------------------------------------

it('returns the 3 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = eaSectorUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/ea-sectors/columns')->assertForbidden();

    $actor = eaSectorUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/ea-sectors/columns')->assertOk()->json('data');

    expect($data['resource'])->toBe('ea-sectors')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['name', 'parent', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['parent']['filterType'])->toBe('set')
        ->and($columns['parent']['sortable'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-013 — rows shape (no N+1: parent eager-loaded)
// ---------------------------------------------------------------------------

it('rows expose id/name/parent{id,name}|null/created_at + per-row actions', function () {
    $actor = eaSectorUserWith(['viewAny', 'view', 'update', 'delete']);
    $root = EaSector::factory()->create(['name' => 'Root']);
    $child = EaSector::factory()->childOf($root)->create(['name' => 'Child']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/ea-sectors/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $rootRow = collect($response->json('items'))->firstWhere('name', 'Root');
    expect($rootRow['parent'])->toBeNull();

    $childRow = collect($response->json('items'))->firstWhere('name', 'Child');
    expect($childRow['parent'])->toBe(['id' => $root->id, 'name' => 'Root'])
        ->and($childRow['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('rows: no N+1 on the parent relation', function () {
    $actor = eaSectorUserWith(['viewAny']);
    $root = EaSector::factory()->create();
    EaSector::factory()->childOf($root)->count(5)->create();
    Sanctum::actingAs($actor);

    EaSector::preventLazyLoading();

    $this->postJson('/api/tables/ea-sectors/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    EaSector::preventLazyLoading(false);
});

// ---------------------------------------------------------------------------
// AC-013 — derived `parent` filter/sort/distinct
// ---------------------------------------------------------------------------

it('filter: parent set filter narrows the rows via whereHas', function () {
    $actor = eaSectorUserWith(['viewAny']);
    $rootA = EaSector::factory()->create(['name' => 'Energy']);
    $rootB = EaSector::factory()->create(['name' => 'Mobility']);
    EaSector::factory()->childOf($rootA)->create(['name' => 'Solar']);
    EaSector::factory()->childOf($rootB)->create(['name' => 'Transit']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/ea-sectors/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['parent' => ['filterType' => 'set', 'values' => ['Energy']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Solar']);
});

it('sort: rows ordered by the derived parent name', function () {
    $actor = eaSectorUserWith(['viewAny']);
    $rootA = EaSector::factory()->create(['name' => 'Zebra Root']);
    $rootB = EaSector::factory()->create(['name' => 'Alpha Root']);
    EaSector::factory()->childOf($rootA)->create(['name' => 'FromZebra']);
    EaSector::factory()->childOf($rootB)->create(['name' => 'FromAlpha']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/ea-sectors/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'parent', 'sort' => 'asc']],
    ])->assertOk();

    $names = collect($response->json('items'))
        ->whereIn('name', ['FromZebra', 'FromAlpha'])
        ->pluck('name')->values()->all();
    expect($names)->toBe(['FromAlpha', 'FromZebra']);
});

it('values: parent → distinct parent names, columnId outside the allow-list → 422', function () {
    $actor = eaSectorUserWith(['viewAny']);
    $rootA = EaSector::factory()->create(['name' => 'Energy']);
    $rootB = EaSector::factory()->create(['name' => 'Mobility']);
    EaSector::factory()->childOf($rootA)->create();
    EaSector::factory()->childOf($rootB)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/ea-sectors/values', ['columnId' => 'parent'])->assertOk();
    expect($response->json('data.values'))->toEqualCanonicalizing(['Energy', 'Mobility']);

    $this->postJson('/api/tables/ea-sectors/values', ['columnId' => 'not_a_column'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

// ---------------------------------------------------------------------------
// AC-014 — bulk-delete respects the restrictive-delete guard
// ---------------------------------------------------------------------------

it('bulk-delete: a sector with children is guarded, not force-deleted', function () {
    $actor = eaSectorUserWith(['viewAny', 'delete']);
    $parent = EaSector::factory()->create();
    EaSector::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/ea-sectors/bulk-delete', ['ids' => [$parent->id]])->assertOk();

    $this->assertDatabaseHas('ea_sectors', ['id' => $parent->id]);
});
