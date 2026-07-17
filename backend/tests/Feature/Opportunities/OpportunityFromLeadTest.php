<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('nonDerivableOpportunityFks')) {
    /**
     * `company_id`/`company_site_id` are mandatory but NEVER BR-1-derivable
     * (amendment rev.1 A-2: no lead/campaign chain to either) — every
     * from-lead POST in this file still needs them explicitly. Since the
     * user directive 2026-07-17 makes `product_lines` mandatory to create,
     * a valid one-row collection ships here too (tests that assert a specific
     * product_lines payload merge the helper FIRST so their own value wins).
     *
     * @return array{company_id: int, company_site_id: int, product_lines: array<int, array{business_function_id: int, product_category_id: int}>}
     */
    function nonDerivableOpportunityFks(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [
            'company_id' => Company::factory()->create()->id,
            'company_site_id' => CompanySite::factory()->create()->id,
            'product_lines' => [
                ['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id],
            ],
        ];
    }
}

if (! function_exists('opportunityFromLeadActor')) {
    /**
     * @param  array<int, string>  $opportunityAbilities
     * @param  array<int, string>  $leadAbilities
     */
    function opportunityFromLeadActor(array $opportunityAbilities, array $leadAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($opportunityAbilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        foreach ($leadAbilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('completeLead')) {
    /**
     * A lead with its own registry (spec 0041 D-3) and NOT NULL on every
     * other BR-1-derivable source column, so every one of the 3 locked
     * fields locks. The category's OWN business_function_id matches the
     * campaign's (amendment rev.3): the product_lines row the defaults
     * endpoint prefills must satisfy the SAME
     * CategoryHierarchy::effectiveBusinessFunction() check the write path
     * enforces, so the two never drift apart in this fixture. Shared with
     * OpportunityFromLeadProductLinesTest (file-size split, engineering.md §6).
     */
    function completeLead(): Lead
    {
        $registry = Registry::factory()->create();
        $source = Source::factory()->create();
        $businessFunction = BusinessFunction::factory()->create();
        $productCategory = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        $campaign = Campaign::factory()->create([
            'source_id' => $source->id,
            'business_function_id' => $businessFunction->id,
            'product_category_id' => $productCategory->id,
        ]);

        return Lead::factory()->create([
            'campaign_id' => $campaign->id,
            'registry_id' => $registry->id,
            'operational_site_id' => OperationalSite::factory()->withAddress()->create()->id,
            'source_id' => null,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-060 — GET opportunity-defaults, lead completo -> 3 campi valorizzati e
// bloccati (spec 0041 D-3: referent_id is no longer derivable, registry_id
// comes from the lead itself, not the campaign; amendment rev.3:
// business_function_id/product_category_id are NO LONGER locked scalars —
// they surface as `product_lines`, see AC-102/103 below)
// ---------------------------------------------------------------------------

it('opportunity-defaults: a complete lead locks all 3 derivable fields (AC-060, AC-050 spec 0041)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.lead_id'))->toBe($lead->id);
    expect($response->json('data.existing_opportunity_id'))->toBeNull();
    expect($response->json('data.values.operational_site_id'))->toBe($lead->operational_site_id);
    expect($response->json('data.values.registry_id'))->toBe($lead->registry_id);
    expect($response->json('data.values.source_id'))->toBe($lead->campaign->source_id);
    expect($response->json('data.values'))->not->toHaveKey('business_function_id');
    expect($response->json('data.values'))->not->toHaveKey('product_category_id');
    expect($response->json('data.locked_fields'))->toEqualCanonicalizing([
        'source_id', 'operational_site_id', 'registry_id',
    ]);
    expect($response->json('data.locked_fields'))->not->toContain('referent_id');
    expect($response->json('data.references.registry.id'))->toBe($lead->registry_id);
});

// AC-102/AC-103 product_lines derivation coverage lives in
// OpportunityFromLeadProductLinesTest (file-size split, engineering.md §6).

// ---------------------------------------------------------------------------
// AC-062 — authz + existing_opportunity_id
// ---------------------------------------------------------------------------

it('opportunity-defaults: 403 without opportunities.create', function () {
    $actor = opportunityFromLeadActor([], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertForbidden();
});

it('opportunity-defaults: 403 without leads.view', function () {
    $actor = opportunityFromLeadActor(['create'], []);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertForbidden();
});

it('opportunity-defaults: 404 for a non-existent lead', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/leads/999999/opportunity-defaults')->assertNotFound();
});

it('opportunity-defaults: existing_opportunity_id is populated when the lead is already linked (AC-062)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $opportunity = Opportunity::factory()->create(['lead_id' => $lead->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")
        ->assertOk()
        ->assertJsonPath('data.existing_opportunity_id', $opportunity->id);
});

// ---------------------------------------------------------------------------
// AC-063 — POST with lead_id: derivation, prohibited, unique
// ---------------------------------------------------------------------------

it('create with lead_id: the server writes the derived attributes (AC-063)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'From lead deal',
        'lead_id' => $lead->id,
    ], nonDerivableOpportunityFks()))->assertCreated();

    $this->assertDatabaseHas('opportunities', [
        'id' => $response->json('data.id'),
        'lead_id' => $lead->id,
        'operational_site_id' => $lead->operational_site_id,
        'registry_id' => $lead->registry_id,
        'source_id' => $lead->campaign->source_id,
    ]);
});

// AC-102/AC-103 create-with-lead product_lines coverage lives in
// OpportunityFromLeadProductLinesTest (file-size split, engineering.md §6).

it('create with lead_id: referent_id is NOT derived/prohibited, freely chosen even with a complete lead (spec 0041 D-3)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $referent = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Freely chosen referent',
        'lead_id' => $lead->id,
        'referent_id' => $referent->id,
    ], nonDerivableOpportunityFks()))->assertCreated();

    expect($response->json('data.referent_id'))->toBe($referent->id);
});

it('create with lead_id: sending a derivable field with a non-null derivation -> 422 prohibited (AC-063)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $otherRegistry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge([
        'name' => 'Conflicting registry',
        'lead_id' => $lead->id,
        'registry_id' => $otherRegistry->id,
    ], nonDerivableOpportunityFks()))->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Opportunity::count())->toBe(0);
});

it('create with lead_id: a second opportunity for the same lead -> 422 unique (AC-063)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge(['name' => 'First', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();

    $this->postJson('/api/opportunities', array_merge(['name' => 'Second', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertStatus(422)->assertJsonValidationErrors('lead_id');

    expect(Opportunity::count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-090 — company_id/company_site_id are NEVER derivable: still required
// even in the from-lead flow
// ---------------------------------------------------------------------------

it('create with lead_id: missing company_id -> 422, not derived from the lead/campaign (AC-090)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $fks = nonDerivableOpportunityFks();
    unset($fks['company_id']);

    $this->postJson('/api/opportunities', array_merge(['name' => 'No company from lead', 'lead_id' => $lead->id], $fks))
        ->assertStatus(422)->assertJsonValidationErrors('company_id');

    expect(Opportunity::count())->toBe(0);
});

it('create with lead_id: missing company_site_id -> 422, not derived from the lead/campaign (AC-090)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $fks = nonDerivableOpportunityFks();
    unset($fks['company_site_id']);

    $this->postJson('/api/opportunities', array_merge(['name' => 'No company site from lead', 'lead_id' => $lead->id], $fks))
        ->assertStatus(422)->assertJsonValidationErrors('company_site_id');

    expect(Opportunity::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-064 — UPDATE lock enforcement
// ---------------------------------------------------------------------------

it('update: a locked field with a DIFFERENT value -> 422 (AC-064)', function () {
    $actor = opportunityFromLeadActor(['create', 'update'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(['name' => 'Locked deal', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();
    $opportunityId = $created->json('data.id');

    $otherRegistry = Registry::factory()->create();

    $this->patchJson("/api/opportunities/{$opportunityId}", ['registry_id' => $otherRegistry->id])
        ->assertStatus(422)->assertJsonValidationErrors('registry_id');
});

it('update: the SAME value for a locked field -> 200, no-op (AC-064)', function () {
    $actor = opportunityFromLeadActor(['create', 'update'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(['name' => 'Locked deal', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();
    $opportunityId = $created->json('data.id');

    $this->patchJson("/api/opportunities/{$opportunityId}", ['registry_id' => $lead->registry_id])
        ->assertOk()
        ->assertJsonPath('data.registry_id', $lead->registry_id);
});

it('update: referent_id is freely editable even when the opportunity has a lead (spec 0041 D-3)', function () {
    $actor = opportunityFromLeadActor(['create', 'update'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(['name' => 'Unlocked referent', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();
    $opportunityId = $created->json('data.id');

    $newReferent = Referent::factory()->create();

    $this->patchJson("/api/opportunities/{$opportunityId}", ['referent_id' => $newReferent->id])
        ->assertOk()
        ->assertJsonPath('data.referent_id', $newReferent->id);
});

it('update: lead_id is prohibited (AC-064)', function () {
    $actor = opportunityFromLeadActor(['update'], []);
    $opportunity = Opportunity::factory()->create();
    $otherLead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['lead_id' => $otherLead->id])
        ->assertStatus(422)->assertJsonValidationErrors('lead_id');
});

it('update: a field with a NULL derivation stays freely editable (AC-064)', function () {
    $actor = opportunityFromLeadActor(['create', 'update'], ['view']);

    $project = Project::factory()->create([
        'business_function_id' => BusinessFunction::factory(),
        'product_category_id' => ProductCategory::factory(),
    ]);
    $campaign = Campaign::factory()->forProject($project)->create();
    // registry_id is omitted here: it is ALWAYS non-null on a Lead (BR-1,
    // spec 0041 D-1), so it always locks — unlike operational_site_id below,
    // which stays a valid null derivation when the lead has no site.
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id, 'operational_site_id' => null]);
    Sanctum::actingAs($actor);

    $initialSite = OperationalSite::factory()->create();

    $created = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Unlocked site',
        'lead_id' => $lead->id,
        'operational_site_id' => $initialSite->id,
    ], nonDerivableOpportunityFks()))->assertCreated();
    $opportunityId = $created->json('data.id');

    $newSite = OperationalSite::factory()->withAddress()->create();

    $this->patchJson("/api/opportunities/{$opportunityId}", ['operational_site_id' => $newSite->id])
        ->assertOk()
        ->assertJsonPath('data.operational_site_id', $newSite->id);
});

// ---------------------------------------------------------------------------
// AC-089 — operational_site_id: locked when the lead has one, mandatory
// (not locked) when it does not
// ---------------------------------------------------------------------------

it('create with lead_id: a lead WITHOUT operational_site_id -> the field is required, not locked (AC-089)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $lead->update(['operational_site_id' => null]);
    Sanctum::actingAs($actor);

    // Omitted entirely -> 422 (mandatory when the lead does not derive it).
    $this->postJson('/api/opportunities', array_merge(['name' => 'No site from lead', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertStatus(422)->assertJsonValidationErrors('operational_site_id');

    // Supplied explicitly -> 201, freely chosen (not locked).
    $site = OperationalSite::factory()->create();

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Site chosen manually',
        'lead_id' => $lead->id,
        'operational_site_id' => $site->id,
    ], nonDerivableOpportunityFks()))->assertCreated();

    expect($response->json('data.operational_site_id'))->toBe($site->id);
    expect($response->json('data.locked_fields'))->not->toContain('operational_site_id');
});

// ---------------------------------------------------------------------------
// AC-065 — detail shape (lead {id,label}, locked_fields) + LeadResource.opportunity
// ---------------------------------------------------------------------------

it('detail: an opportunity from a lead exposes lead {id,label} and locked_fields (AC-065)', function () {
    $actor = opportunityFromLeadActor(['create', 'view'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(['name' => 'Detail check', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();
    $opportunityId = $created->json('data.id');

    $this->getJson("/api/opportunities/{$opportunityId}")
        ->assertOk()
        ->assertJsonPath('data.lead.id', $lead->id)
        ->assertJsonPath('data.lead.label', $lead->registry->name)
        ->assertJsonPath('data.locked_fields', [
            'source_id', 'operational_site_id', 'registry_id',
        ]);
});

it('LeadResource exposes opportunity {id,name}|null (AC-065)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$lead->id}")->assertOk()->assertJsonPath('data.opportunity', null);

    $created = $this->postJson('/api/opportunities', array_merge(['name' => 'Linked deal', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();

    $this->getJson("/api/leads/{$lead->id}")
        ->assertOk()
        ->assertJsonPath('data.opportunity', ['id' => $created->json('data.id'), 'name' => 'Linked deal']);
});
