<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('businessFunctionUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function businessFunctionUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-003 — columns config
// ---------------------------------------------------------------------------

it('returns the 6 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = businessFunctionUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/business-functions/columns')->assertForbidden();

    $actor = businessFunctionUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/business-functions/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('business-functions')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['name', 'is_business_unit', 'is_business_service', 'manager', 'users', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterType'])->toBe('text')
        ->and($columns['is_business_unit']['sortable'])->toBeTrue()
        ->and($columns['is_business_unit']['filterType'])->toBe('set')
        ->and($columns['is_business_service']['filterType'])->toBe('set')
        ->and($columns['manager']['sortable'])->toBeTrue()
        ->and($columns['manager']['filterType'])->toBe('set')
        ->and($columns['users']['sortable'])->toBeFalse()
        ->and($columns['users']['type'])->toBe('tags')
        ->and($columns['created_at']['filterType'])->toBe('date');
});

it('hides action keys the user has no permission for', function () {
    $actor = businessFunctionUserWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/business-functions/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// AC-004 — rows shape (manager/users avatar objects), actions, no N+1
// ---------------------------------------------------------------------------

it('rows expose manager/users objects and per-row actions, no sensitive fields', function () {
    $actor = businessFunctionUserWith(['viewAny', 'view', 'update', 'delete']);
    $manager = User::factory()->create(['name' => 'Manager One']);
    $member = User::factory()->create(['name' => 'Member One']);
    $target = BusinessFunction::factory()->create(['name' => 'Finance', 'manager_id' => $manager->id]);
    $target->users()->sync([$member->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/rows', [
        'startRow' => 0,
        'endRow' => 25,
    ])->assertOk();

    $row = collect($response->json('items'))->firstWhere('name', 'Finance');

    expect($row)->not->toBeNull()
        ->and($row['manager'])->toMatchArray(['id' => $manager->id, 'name' => 'Manager One'])
        ->and($row['manager'])->toHaveKey('avatar_url')
        ->and($row['users'])->toHaveCount(1)
        ->and($row['users'][0])->toMatchArray(['id' => $member->id, 'name' => 'Member One'])
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete'])
        ->and($row)->not->toHaveKey('password');
});

it('rows resolve manager/users with a bounded query count (no N+1)', function () {
    $actor = businessFunctionUserWith(['viewAny']);

    foreach (range(1, 5) as $i) {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $bf = BusinessFunction::factory()->create(['manager_id' => $manager->id]);
        $bf->users()->sync([$member->id]);
    }

    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->postJson('/api/tables/business-functions/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonCount(5, 'items');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A fixed, small number of queries regardless of row count (base query +
    // eager-loaded manager/manager avatar/users/users avatar + count query),
    // never one query per row.
    expect($queryCount)->toBeLessThan(10);
});

it('a manager with no associated users has an empty users array', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    BusinessFunction::factory()->create(['name' => 'Lonely', 'manager_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Lonely');

    expect($row['manager'])->toBeNull()
        ->and($row['users'])->toBe([]);
});

it('filters rows by the derived manager set filter (whereHas by name)', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $alice = User::factory()->create(['name' => 'Alice Manager']);
    $bob = User::factory()->create(['name' => 'Bob Manager']);
    BusinessFunction::factory()->create(['name' => 'Team Alice', 'manager_id' => $alice->id]);
    BusinessFunction::factory()->create(['name' => 'Team Bob', 'manager_id' => $bob->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['manager' => ['filterType' => 'set', 'values' => ['Alice Manager']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Team Alice']);
});

it('sorts rows by the derived manager name via a correlated subquery', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $zed = User::factory()->create(['name' => 'Zed']);
    $amy = User::factory()->create(['name' => 'Amy']);
    BusinessFunction::factory()->create(['name' => 'Z-func', 'manager_id' => $zed->id]);
    BusinessFunction::factory()->create(['name' => 'A-func', 'manager_id' => $amy->id]);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/business-functions/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'manager', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-func', $names, true))->toBeLessThan(array_search('Z-func', $names, true));
});

// ---------------------------------------------------------------------------
// AC-005 — /values distinct values for manager and users
// ---------------------------------------------------------------------------

it('resolves distinct manager names via /values', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $alice = User::factory()->create(['name' => 'Alice Manager']);
    BusinessFunction::factory()->create(['manager_id' => $alice->id]);
    BusinessFunction::factory()->create(['manager_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/values', ['columnId' => 'manager'])->assertOk();

    expect($response->json('data.values'))->toBe(['Alice Manager']);
});

it('resolves distinct associated user names via /values', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $carol = User::factory()->create(['name' => 'Carol Member']);
    $bf = BusinessFunction::factory()->create();
    $bf->users()->sync([$carol->id]);
    BusinessFunction::factory()->create(); // no members
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/values', ['columnId' => 'users'])->assertOk();

    expect($response->json('data.values'))->toBe(['Carol Member']);
});

it('/values search narrows the distinct manager names', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    $alice = User::factory()->create(['name' => 'Alice Manager']);
    $bob = User::factory()->create(['name' => 'Bob Manager']);
    BusinessFunction::factory()->create(['manager_id' => $alice->id]);
    BusinessFunction::factory()->create(['manager_id' => $bob->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/business-functions/values', [
        'columnId' => 'manager',
        'search' => 'alice',
    ])->assertOk();

    expect($response->json('data.values'))->toBe(['Alice Manager']);
});

it('422 on the values endpoint when columnId is not filterable', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/business-functions/values', ['columnId' => 'id'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});
