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
     * from-lead POST in this file still needs them explicitly.
     *
     * @return array{company_id: int, company_site_id: int}
     */
    function nonDerivableOpportunityFks(): array
    {
        return [
            'company_id' => Company::factory()->create()->id,
            'company_site_id' => CompanySite::factory()->create()->id,
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

/**
 * A lead whose campaign is standalone (no project) and NOT NULL on every
 * BR-1-derivable source column, so every one of the 6 fields locks.
 */
function completeLead(): Lead
{
    $registry = Registry::factory()->create();
    $source = Source::factory()->create();
    $businessFunction = BusinessFunction::factory()->create();
    $productCategory = ProductCategory::factory()->create();

    $campaign = Campaign::factory()->create([
        'registry_id' => $registry->id,
        'source_id' => $source->id,
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $productCategory->id,
    ]);

    return Lead::factory()->create([
        'campaign_id' => $campaign->id,
        'referent_id' => Referent::factory()->create()->id,
        'operational_site_id' => OperationalSite::factory()->withAddress()->create()->id,
        'source_id' => null,
    ]);
}

// ---------------------------------------------------------------------------
// AC-060 — GET opportunity-defaults, lead completo -> 6 campi valorizzati e bloccati
// ---------------------------------------------------------------------------

it('opportunity-defaults: a complete lead locks all 6 derivable fields (AC-060)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.lead_id'))->toBe($lead->id);
    expect($response->json('data.existing_opportunity_id'))->toBeNull();
    expect($response->json('data.values.referent_id'))->toBe($lead->referent_id);
    expect($response->json('data.values.operational_site_id'))->toBe($lead->operational_site_id);
    expect($response->json('data.values.registry_id'))->toBe($lead->campaign->registry_id);
    expect($response->json('data.values.source_id'))->toBe($lead->campaign->source_id);
    expect($response->json('data.values.business_function_id'))->toBe($lead->campaign->business_function_id);
    expect($response->json('data.values.product_category_id'))->toBe($lead->campaign->product_category_id);
    expect($response->json('data.locked_fields'))->toEqualCanonicalizing([
        'referent_id', 'source_id', 'operational_site_id', 'registry_id', 'business_function_id', 'product_category_id',
    ]);
    expect($response->json('data.references.referent.id'))->toBe($lead->referent_id);
});

// ---------------------------------------------------------------------------
// AC-061 — campaign linked to a project: effective values; a null-derivable
// field is neither in values nor locked_fields
// ---------------------------------------------------------------------------

it('opportunity-defaults: business_function/product_category come from the linked PROJECT\'s effective values (AC-061)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $businessFunction = BusinessFunction::factory()->create();
    $productCategory = ProductCategory::factory()->create();

    $project = Project::factory()->create([
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $productCategory->id,
    ]);
    $campaign = Campaign::factory()->forProject($project)->create();

    $lead = Lead::factory()->create([
        'campaign_id' => $campaign->id,
        'operational_site_id' => null,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.values.business_function_id'))->toBe($businessFunction->id);
    expect($response->json('data.values.product_category_id'))->toBe($productCategory->id);
    expect($response->json('data.values.operational_site_id'))->toBeNull();
    expect($response->json('data.locked_fields'))->not->toContain('operational_site_id');
});

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
        'referent_id' => $lead->referent_id,
        'operational_site_id' => $lead->operational_site_id,
        'registry_id' => $lead->campaign->registry_id,
        'source_id' => $lead->campaign->source_id,
        'business_function_id' => $lead->campaign->business_function_id,
        'product_category_id' => $lead->campaign->product_category_id,
    ]);
});

it('create with lead_id: sending a derivable field with a non-null derivation -> 422 prohibited (AC-063)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $otherReferent = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunities', array_merge([
        'name' => 'Conflicting referent',
        'lead_id' => $lead->id,
        'referent_id' => $otherReferent->id,
    ], nonDerivableOpportunityFks()))->assertStatus(422)->assertJsonValidationErrors('referent_id');

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

    $otherReferent = Referent::factory()->create();

    $this->patchJson("/api/opportunities/{$opportunityId}", ['referent_id' => $otherReferent->id])
        ->assertStatus(422)->assertJsonValidationErrors('referent_id');
});

it('update: the SAME value for a locked field -> 200, no-op (AC-064)', function () {
    $actor = opportunityFromLeadActor(['create', 'update'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunities', array_merge(['name' => 'Locked deal', 'lead_id' => $lead->id], nonDerivableOpportunityFks()))
        ->assertCreated();
    $opportunityId = $created->json('data.id');

    $this->patchJson("/api/opportunities/{$opportunityId}", ['referent_id' => $lead->referent_id])
        ->assertOk()
        ->assertJsonPath('data.referent_id', $lead->referent_id);
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
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id, 'operational_site_id' => null]);
    Sanctum::actingAs($actor);

    $initialSite = OperationalSite::factory()->create();

    $created = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Unlocked site',
        'lead_id' => $lead->id,
        'registry_id' => Registry::factory()->create()->id,
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
        ->assertJsonPath('data.lead.label', $lead->referent->name)
        ->assertJsonPath('data.locked_fields', [
            'referent_id', 'source_id', 'operational_site_id', 'registry_id', 'business_function_id', 'product_category_id',
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
