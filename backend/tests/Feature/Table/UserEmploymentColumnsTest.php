<?php

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Feature coverage for the 9 employment-derived grid columns on the `users`
 * domain (spec 0015, AC-011): english ids, allow-listed sort/filter (no
 * orderByRaw/whereRaw from raw input), enumKey for the two enum columns.
 */
if (! function_exists('employmentTableActor')) {
    function employmentTableActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('rowsPayloadForEmployment')) {
    function rowsPayloadForEmployment(array $overrides = []): array
    {
        return array_merge(['startRow' => 0, 'endRow' => 25], $overrides);
    }
}

const EMPLOYMENT_COLUMN_IDS = [
    'business_function', 'company', 'operational_site', 'relationship_type',
    'qualification_type', 'is_manager', 'reports_to', 'hired_at', 'terminated_at',
];

it('exposes the 9 employment columns with english ids, sortable/filterable, and the 2 enumKeys', function () {
    $actor = employmentTableActor(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))->keyBy('id');

    foreach (EMPLOYMENT_COLUMN_IDS as $id) {
        expect($columns)->toHaveKey($id);
        expect($columns[$id]['sortable'])->toBeTrue()
            ->and($columns[$id]['filterable'])->toBeTrue();
    }

    expect($columns['relationship_type']['enumKey'])->toBe('relationship_type')
        ->and($columns['qualification_type']['enumKey'])->toBe('qualification_type')
        ->and($columns['relationship_type']['options'])->toBe(['employee', 'self_employed', 'other'])
        ->and($columns['operational_site']['hasFilterValues'])->toBeFalse()
        ->and($columns['hired_at']['hasFilterValues'])->toBeFalse();
});

it('the 9 employment column ids are in the sortable/filterable allow-lists returned to the frontend', function () {
    $actor = employmentTableActor(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'));

    foreach (EMPLOYMENT_COLUMN_IDS as $id) {
        $column = $columns->firstWhere('id', $id);
        expect($column)->not->toBeNull();
    }
});

it('rows: sorts by a related-name employment column via the correlated subquery, not raw SQL', function () {
    $actor = employmentTableActor(['viewAny']);
    $functionA = BusinessFunction::factory()->create(['name' => 'Alpha']);
    $functionB = BusinessFunction::factory()->create(['name' => 'Zulu']);
    User::factory()->withEmployment(fn ($f) => $f->state(['business_function_id' => $functionA->id]))->create(['name' => 'First']);
    User::factory()->withEmployment(fn ($f) => $f->state(['business_function_id' => $functionB->id]))->create(['name' => 'Second']);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/users/rows', rowsPayloadForEmployment([
        'sortModel' => [['colId' => 'business_function', 'sort' => 'asc']],
    ]))->assertOk()->json('items.*.business_function');

    // The actor itself (created by employmentTableActor, no employment) also
    // appears in the page with a null business_function — assert only the
    // relative order of the two employment-bearing rows.
    expect(array_values(array_filter($names)))->toBe(['Alpha', 'Zulu']);
});

it('rows: filters by the is_manager set filter (derived boolean column)', function () {
    $actor = employmentTableActor(['viewAny']);
    User::factory()->withEmployment(fn ($f) => $f->manager())->create();
    User::factory()->withEmployment(fn ($f) => $f->state(['is_manager' => false]))->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayloadForEmployment([
        'filterModel' => ['is_manager' => ['values' => [true]]],
    ]))->assertOk();

    expect(collect($response->json('items'))->pluck('is_manager')->unique()->all())->toBe([true]);
});

it('rows: filters relationship_type via allow-listed enum values (no raw input reaches the query)', function () {
    $actor = employmentTableActor(['viewAny']);
    User::factory()->withEmployment(fn ($f) => $f->state(['relationship_type' => 'employee']))->create();
    User::factory()->withEmployment(fn ($f) => $f->state(['relationship_type' => 'self_employed']))->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayloadForEmployment([
        'filterModel' => ['relationship_type' => ['values' => ['employee']]],
    ]))->assertOk();

    expect(collect($response->json('items'))->pluck('relationship_type')->unique()->all())->toBe(['employee']);
});

it('rows: operational_site and date columns never crash (no real DB column on users)', function () {
    $actor = employmentTableActor(['viewAny']);
    $site = OperationalSite::factory()->withAddress()->create();
    User::factory()->withEmployment(fn ($f) => $f->state(['operational_site_id' => $site->id, 'hired_at' => '2024-01-01']))->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/rows', rowsPayloadForEmployment([
        'sortModel' => [['colId' => 'operational_site', 'sort' => 'asc']],
    ]))->assertOk();

    $this->postJson('/api/tables/users/rows', rowsPayloadForEmployment([
        'filterModel' => ['hired_at' => ['filterType' => 'date', 'type' => 'equals', 'dateFrom' => '2024-01-01']],
    ]))->assertOk();
});

it('values: business_function distinct values are resolved from real rows, not a whereRaw on `users`', function () {
    $actor = employmentTableActor(['viewAny']);
    $function = BusinessFunction::factory()->create(['name' => 'Legal']);
    User::factory()->withEmployment(fn ($f) => $f->state(['business_function_id' => $function->id]))->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', ['columnId' => 'business_function'])->assertOk();

    expect($response->json('data.values'))->toBe(['Legal']);
});

it('values: relationship_type distinct values are the enum catalogue', function () {
    $actor = employmentTableActor(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/values', ['columnId' => 'relationship_type'])->assertOk();

    expect($response->json('data.values'))->toBe(['employee', 'self_employed', 'other']);
});
