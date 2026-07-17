<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\ProductCategory;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * `product_lines` (spec 0040, amendment rev.3) in the "create from lead"
 * flow: AC-102 (defaults resolver) + AC-103 (editable/removable, never
 * BR-2-locked). Split out of OpportunityFromLeadTest (file-size limit,
 * engineering.md §6) — reuses its `completeLead()`/`opportunityFromLeadActor()`/
 * `nonDerivableOpportunityFks()` helpers (globally declared, guarded with
 * `function_exists`).
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-102 — GET opportunity-defaults: product_lines derivation
// ---------------------------------------------------------------------------

it('opportunity-defaults: product_lines carries the campaign\'s EFFECTIVE business function + product category (AC-102)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.product_lines'))->toHaveCount(1);
    expect($response->json('data.product_lines.0.business_function.id'))->toBe($lead->campaign->business_function_id);
    expect($response->json('data.product_lines.0.product_category.id'))->toBe($lead->campaign->product_category_id);
});

it('opportunity-defaults: business_function/product_category come from the linked PROJECT\'s effective values (AC-061/AC-102)', function () {
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

    expect($response->json('data.product_lines.0.business_function.id'))->toBe($businessFunction->id);
    expect($response->json('data.product_lines.0.product_category.id'))->toBe($productCategory->id);
    expect($response->json('data.values.operational_site_id'))->toBeNull();
    expect($response->json('data.locked_fields'))->not->toContain('operational_site_id');
});

it('opportunity-defaults: product_lines is empty when the campaign has neither business function nor product category (AC-102)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $campaign = Campaign::factory()->create(['business_function_id' => null, 'product_category_id' => null]);
    $lead = Lead::factory()->create(['campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.product_lines'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-103 — create with lead_id: NOT auto-derived server-side, editable/removable
// ---------------------------------------------------------------------------

it('create with lead_id: product_lines is NOT auto-derived server-side — the client must submit it explicitly (AC-102/AC-103)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'From lead, no product lines submitted',
        'lead_id' => $lead->id,
    ], nonDerivableOpportunityFks()))->assertCreated();

    expect($response->json('data.product_lines'))->toBe([]);
    $this->assertDatabaseCount('opportunity_product_lines', 0);
});

it('create with lead_id: the client-submitted product_lines (matching the defaults prefill) persists and is editable/removable (AC-102/AC-103)', function () {
    $actor = opportunityFromLeadActor(['create', 'update'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'From lead, product lines submitted',
        'lead_id' => $lead->id,
        'product_lines' => [
            ['business_function_id' => $lead->campaign->business_function_id, 'product_category_id' => $lead->campaign->product_category_id],
        ],
    ], nonDerivableOpportunityFks()))->assertCreated();

    $opportunityId = $response->json('data.id');
    expect($response->json('data.product_lines'))->toHaveCount(1);

    // Editable/removable — NOT a BR-2-locked field: a subsequent PATCH clearing
    // it succeeds (200), unlike a genuinely locked field which would 422.
    $this->patchJson("/api/opportunities/{$opportunityId}", ['product_lines' => []])->assertOk();
    $this->assertDatabaseCount('opportunity_product_lines', 0);
});
