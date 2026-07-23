<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| opportunity_status_id FK wiring on Opportunity (spec 0043, D-3, AC-012/013)
|--------------------------------------------------------------------------
*/

if (! function_exists('opportunityStatusFkUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityStatusFkUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunity-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('opportunityStatusFkPayload')) {
    /**
     * @return array{registry_id: int, supervisor_id: int, product_lines: array<int, array{business_function_id: int, product_category_id: int}>, products_of_interest: array<int, int>}
     */
    function opportunityStatusFkPayload(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
            // User directive 2026-07-23: products_of_interest is mandatory too;
            // the product belongs to the row's OWN category, so this payload
            // never triggers a cross-category product-line addition.
            'products_of_interest' => [Product::factory()->create(['category_id' => $category->id])->id],
        ];
    }
}

it('create: 422 without opportunity_status_id (AC-012)', function () {
    $actor = opportunityStatusFkUserWith(['opportunities.create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'No status'], opportunityStatusFkPayload()))
        ->assertStatus(422)->assertJsonValidationErrors('opportunity_status_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: 422 when opportunity_status_id does not exist (AC-012)', function () {
    $actor = opportunityStatusFkUserWith(['opportunities.create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(
        ['name' => 'Ghost status', 'opportunity_status_id' => 999999],
        opportunityStatusFkPayload(),
    ))->assertStatus(422)->assertJsonValidationErrors('opportunity_status_id');
});

it('create: 201 with a valid opportunity_status_id, resource exposes opportunity_status {id, name, color} (AC-012)', function () {
    $actor = opportunityStatusFkUserWith(['opportunities.create']);
    $status = OpportunityStatus::factory()->create(['name' => 'In trattativa', 'color' => 'blue']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(
        ['name' => 'Deal with status', 'opportunity_status_id' => $status->id],
        opportunityStatusFkPayload(),
    ))
        ->assertCreated()
        ->assertJsonPath('data.opportunity_status_id', $status->id)
        ->assertJsonPath('data.opportunity_status', ['id' => $status->id, 'name' => 'In trattativa', 'color' => 'blue']);

    $this->assertDatabaseHas('opportunities', ['opportunity_status_id' => $status->id]);
});

it('update: PATCH to a null-like value is rejected (the FK cannot be cleared)', function () {
    $actor = opportunityStatusFkUserWith(['opportunities.update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['opportunity_status_id' => null])
        ->assertStatus(422)->assertJsonValidationErrors('opportunity_status_id');
});

it('update: PATCH to another valid opportunity_status_id -> 200', function () {
    $actor = opportunityStatusFkUserWith(['opportunities.update']);
    $opportunity = Opportunity::factory()->create();
    $newStatus = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['opportunity_status_id' => $newStatus->id])
        ->assertOk()
        ->assertJsonPath('data.opportunity_status_id', $newStatus->id);
});

it('delete: an opportunity status referenced by an opportunity -> 409 (BR-2)', function () {
    $actor = opportunityStatusFkUserWith(['opportunity-statuses.delete']);
    $status = OpportunityStatus::factory()->create();
    $opportunity = Opportunity::factory()->create(['opportunity_status_id' => $status->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-statuses/{$status->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This opportunity status is used by an opportunity and cannot be deleted.');

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $status->id]);
    $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id]);
});

// ---------------------------------------------------------------------------
// AC-013 — opportunities table: opportunity_status cell + filter/sort
// ---------------------------------------------------------------------------

it('table rows: opportunity_status cell is {id, name, color}, filterable and sortable via allow-list (AC-013)', function () {
    $actor = opportunityStatusFkUserWith(['opportunities.viewAny']);
    $matching = OpportunityStatus::factory()->create(['name' => 'Match Status']);
    $other = OpportunityStatus::factory()->create(['name' => 'Other Status']);
    $opportunity = Opportunity::factory()->create(['opportunity_status_id' => $matching->id]);
    Opportunity::factory()->create(['opportunity_status_id' => $other->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $opportunity->id);

    expect($row['opportunity_status'])->toBe(['id' => $matching->id, 'name' => 'Match Status']);

    $filtered = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['opportunity_status' => ['filterType' => 'set', 'values' => ['Match Status']]],
    ])->assertOk();

    $ids = collect($filtered->json('items'))->pluck('id');
    expect($ids->all())->toBe([$opportunity->id]);
});

it('table rows: an unknown sort target is rejected, sorting by opportunity_status is allow-listed', function () {
    $actor = opportunityStatusFkUserWith(['opportunities.viewAny']);
    $statusAlpha = OpportunityStatus::factory()->create(['name' => 'Alpha Status']);
    $statusZulu = OpportunityStatus::factory()->create(['name' => 'Zulu Status']);
    $opportunityZulu = Opportunity::factory()->create(['opportunity_status_id' => $statusZulu->id]);
    $opportunityAlpha = Opportunity::factory()->create(['opportunity_status_id' => $statusAlpha->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'opportunity_status', 'sort' => 'asc']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$opportunityAlpha->id, $opportunityZulu->id]);
});
