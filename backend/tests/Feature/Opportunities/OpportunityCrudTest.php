<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('mandatoryOpportunityFks')) {
    /**
     * The mandatory create payload beyond `name`: the 4 mandatory relation FKs
     * (amendment rev.1 A-2: registry_id/company_id/company_site_id/
     * operational_site_id) plus a valid one-row `product_lines` collection
     * (user directive 2026-07-17: at least one row is required to create).
     * Each a freshly created row. Tests asserting a specific `product_lines`
     * payload merge this helper FIRST so their own value overrides it.
     *
     * @return array{registry_id: int, company_id: int, company_site_id: int, operational_site_id: int, product_lines: array<int, array{business_function_id: int, product_category_id: int}>}
     */
    function mandatoryOpportunityFks(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'registry_id' => Registry::factory()->create()->id,
            'company_id' => Company::factory()->create()->id,
            'company_site_id' => CompanySite::factory()->create()->id,
            'operational_site_id' => OperationalSite::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
        ];
    }
}

// ---------------------------------------------------------------------------
// create (AC-012/AC-013/AC-014/AC-016/AC-017/AC-082 — SOSTITUISCE la parte
// "solo name+registry_id" di AC-010/AC-011: i 5 mandatory sono ora name +
// registry_id/company_id/company_site_id/operational_site_id)
// ---------------------------------------------------------------------------

it('create: with the mandatory fields only -> 201, every optional scalar null (AC-082)', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    // product_lines is a to-many relation, not an `opportunities` column: it
    // is asserted on the response/pivot, not via assertDatabaseHas.
    $scalarFks = Arr::except($fks, 'product_lines');
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge(['name' => 'Deal Alpha'], $fks))
        ->assertCreated();

    $opportunityId = $response->json('data.id');
    $this->assertDatabaseHas('opportunities', array_merge(['id' => $opportunityId, 'name' => 'Deal Alpha'], $scalarFks, [
        'referent_id' => null,
        'commercial_id' => null,
        'reporter_id' => null,
        'supervisor_id' => null,
        'source_id' => null,
        'lead_id' => null,
    ]));
    expect($response->json('data.product_lines'))->toHaveCount(1);
});

it('create: 201, response shape matches the frozen contract', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    $registry = Registry::find($fks['registry_id']);
    $registry->update(['name' => 'Acme Spa']);
    $company = Company::find($fks['company_id']);
    $company->update(['denomination' => 'Acme Group']);
    $referent = Referent::factory()->create(['name' => 'Ada Contact']);
    $supervisor = User::factory()->create(['name' => 'Sara Supervisor']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge($fks, [
        'name' => 'Deal Beta',
        'referent_id' => $referent->id,
        'supervisor_id' => $supervisor->id,
        'start_date' => '2026-02-01',
        'estimated_value' => 12345.67,
        'expected_close_date' => '2026-06-01',
        'success_probability' => 60,
    ]))->assertCreated()
        ->assertJsonPath('data.name', 'Deal Beta')
        ->assertJsonPath('data.registry', ['id' => $registry->id, 'name' => 'Acme Spa'])
        ->assertJsonPath('data.company', ['id' => $company->id, 'name' => 'Acme Group'])
        ->assertJsonPath('data.referent', ['id' => $referent->id, 'name' => 'Ada Contact'])
        ->assertJsonPath('data.supervisor', ['id' => $supervisor->id, 'name' => 'Sara Supervisor'])
        ->assertJsonPath('data.estimated_value', '12345.67')
        ->assertJsonPath('data.success_probability', 60)
        ->assertJsonPath('data.lead_id', null)
        ->assertJsonPath('data.lead', null)
        ->assertJsonPath('data.locked_fields', []);

    $opportunity = Opportunity::sole();
    expect($opportunity->start_date->format('Y-m-d'))->toBe('2026-02-01');
    expect($opportunity->expected_close_date->format('Y-m-d'))->toBe('2026-06-01');
});

it('create: missing name -> 422 on that field, no row created', function () {
    $actor = opportunityUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', mandatoryOpportunityFks())
        ->assertStatus(422)->assertJsonValidationErrors('name');

    expect(Opportunity::count())->toBe(0);
});

it('create: missing registry_id -> 422 on that field, no row created', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    unset($fks['registry_id']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'No Registry'], $fks))
        ->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: missing company_id -> 422 on that field, no row created (AC-082)', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    unset($fks['company_id']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'No Company'], $fks))
        ->assertStatus(422)->assertJsonValidationErrors('company_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: missing company_site_id -> 422 on that field, no row created (AC-082)', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    unset($fks['company_site_id']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'No Company Site'], $fks))
        ->assertStatus(422)->assertJsonValidationErrors('company_site_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: missing operational_site_id -> 422 on that field, no row created (AC-082)', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    unset($fks['operational_site_id']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'No Operational Site'], $fks))
        ->assertStatus(422)->assertJsonValidationErrors('operational_site_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: 403 without opportunities.create, no row created (AC-012)', function () {
    $actor = opportunityUserWith([]);
    $fks = mandatoryOpportunityFks();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'Nope'], $fks))
        ->assertForbidden();

    expect(Opportunity::count())->toBe(0);
});

it('create: a non-existent registry_id -> 422 (exists), not 500 (AC-017)', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    $fks['registry_id'] = 999999;
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'Ghost registry'], $fks))
        ->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: a non-existent company_id -> 422 (exists), not 500 (AC-017)', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    $fks['company_id'] = 999999;
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'Ghost company'], $fks))
        ->assertStatus(422)->assertJsonValidationErrors('company_id');

    expect(Opportunity::count())->toBe(0);
});

it('create: success_probability 101 or -1 -> 422; 0 and 100 accepted (AC-014)', function () {
    $actor = opportunityUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'Too high', 'success_probability' => 101], mandatoryOpportunityFks()))
        ->assertStatus(422)->assertJsonValidationErrors('success_probability');

    $this->postJson('/api/opportunities', array_merge(['name' => 'Too low', 'success_probability' => -1], mandatoryOpportunityFks()))
        ->assertStatus(422)->assertJsonValidationErrors('success_probability');

    $this->postJson('/api/opportunities', array_merge(['name' => 'Zero', 'success_probability' => 0], mandatoryOpportunityFks()))
        ->assertCreated();

    $this->postJson('/api/opportunities', array_merge(['name' => 'Hundred', 'success_probability' => 100], mandatoryOpportunityFks()))
        ->assertCreated();
});

it('create: estimated_value negative -> 422 (AC-014)', function () {
    $actor = opportunityUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'Negative value', 'estimated_value' => -1], mandatoryOpportunityFks()))
        ->assertStatus(422)->assertJsonValidationErrors('estimated_value');
});

// ---------------------------------------------------------------------------
// create: manager_slots (AC-015)
// ---------------------------------------------------------------------------

it('create: manager_slots [u1, null, u2] -> pivot with position 1 and 3, gap preserved (AC-015)', function () {
    $actor = opportunityUserWith(['create']);
    $userOne = User::factory()->create();
    $userTwo = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Managed deal',
        'manager_slots' => [$userOne->id, null, $userTwo->id],
    ], mandatoryOpportunityFks()))->assertCreated();

    $opportunityId = $response->json('data.id');

    $this->assertDatabaseHas('opportunity_user', ['opportunity_id' => $opportunityId, 'user_id' => $userOne->id, 'position' => 1]);
    $this->assertDatabaseHas('opportunity_user', ['opportunity_id' => $opportunityId, 'user_id' => $userTwo->id, 'position' => 3]);
    expect($response->json('data.managers'))->toBe([
        ['id' => $userOne->id, 'name' => $userOne->name, 'position' => 1],
        ['id' => $userTwo->id, 'name' => $userTwo->name, 'position' => 3],
    ]);
});

it('create: a duplicate user across manager slots -> 422 (AC-015)', function () {
    $actor = opportunityUserWith(['create']);
    $user = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge([
        'name' => 'Duplicate manager',
        'manager_slots' => [$user->id, $user->id],
    ], mandatoryOpportunityFks()))->assertStatus(422)->assertJsonValidationErrors('manager_slots');
});

it('create: more than 4 filled manager slots -> 422 (AC-015)', function () {
    $actor = opportunityUserWith(['create']);
    $users = User::factory()->count(5)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge([
        'name' => 'Too many managers',
        'manager_slots' => $users->pluck('id')->all(),
    ], mandatoryOpportunityFks()))->assertStatus(422)->assertJsonValidationErrors('manager_slots');
});

// ---------------------------------------------------------------------------
// update (AC-013)
// ---------------------------------------------------------------------------

it('update: PATCH with only estimated_value -> 200, only that field changes (AC-013)', function () {
    $actor = opportunityUserWith(['update']);
    $opportunity = Opportunity::factory()->create(['name' => 'Original name', 'estimated_value' => 100]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['estimated_value' => 999.99])
        ->assertOk()
        ->assertJsonPath('data.estimated_value', '999.99');

    $this->assertDatabaseHas('opportunities', [
        'id' => $opportunity->id,
        'name' => 'Original name',
        'registry_id' => $opportunity->registry_id,
        'estimated_value' => 999.99,
    ]);
});

// ---------------------------------------------------------------------------
// show/delete authz (AC-012)
// ---------------------------------------------------------------------------

it('show: 403 without opportunities.view', function () {
    $actor = opportunityUserWith([]);
    $target = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent opportunity', function () {
    $actor = opportunityUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/opportunities/999999')->assertNotFound();
});

it('delete: 403 without opportunities.delete', function () {
    $actor = opportunityUserWith([]);
    $target = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunities/{$target->id}")->assertForbidden();
});

it('delete: 204, removes the opportunity and its manager pivot rows; the linked lead is untouched (AC-016)', function () {
    $actor = opportunityUserWith(['delete']);
    $lead = Lead::factory()->create();
    $target = Opportunity::factory()->create(['lead_id' => $lead->id]);
    $manager = User::factory()->create();
    $target->managers()->sync([$manager->id => ['position' => 1]]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunities/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('opportunities', ['id' => $target->id]);
    $this->assertDatabaseMissing('opportunity_user', ['opportunity_id' => $target->id]);
    $this->assertDatabaseHas('leads', ['id' => $lead->id]);
});

it('delete: 404 for a non-existent opportunity', function () {
    $actor = opportunityUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/opportunities/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// response shape sanity for the remaining relation summaries
// ---------------------------------------------------------------------------

it('exposes company/company_site/operational_site/source/product_lines summaries', function () {
    $actor = opportunityUserWith(['create']);
    $fks = mandatoryOpportunityFks();
    $company = Company::find($fks['company_id']);
    $company->update(['denomination' => 'Acme Group']);
    $companySite = CompanySite::find($fks['company_site_id']);
    $companySite->update(['name' => 'Sede Nord']);
    $businessFunction = BusinessFunction::factory()->create(['name' => 'Vendite']);
    $source = Source::factory()->create(['name' => 'Fiera']);
    $productCategory = ProductCategory::factory()->create(['name' => 'Servizi Cloud', 'business_function_id' => $businessFunction->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge($fks, [
        'name' => 'Full relations',
        'source_id' => $source->id,
        'product_lines' => [
            ['business_function_id' => $businessFunction->id, 'product_category_id' => $productCategory->id],
        ],
    ]))->assertCreated()
        ->assertJsonPath('data.company', ['id' => $company->id, 'name' => 'Acme Group'])
        ->assertJsonPath('data.company_site', ['id' => $companySite->id, 'name' => 'Sede Nord'])
        ->assertJsonPath('data.source', ['id' => $source->id, 'name' => 'Fiera'])
        ->assertJsonPath('data.operational_site.id', $fks['operational_site_id']);

    $productLines = $response->json('data.product_lines');
    expect($productLines)->toHaveCount(1);
    expect($productLines[0]['business_function'])->toBe(['id' => $businessFunction->id, 'name' => 'Vendite']);
    expect($productLines[0]['product_category'])->toBe(['id' => $productCategory->id, 'name' => 'Servizi Cloud']);
});
