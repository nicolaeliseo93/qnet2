<?php

use App\Enums\StatusSystemKey;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\ProductCategory;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadConversionActor')) {
    /**
     * @param  array<int, string>  $leadAbilities
     * @param  array<int, string>  $opportunityAbilities
     */
    function leadConversionActor(array $leadAbilities, array $opportunityAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($leadAbilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        foreach ($opportunityAbilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('convertibleLeadFixture')) {
    /**
     * A POST /api/leads payload with `convert_to_opportunity: true` plus
     * every reference needed to make the derivation succeed: a campaign
     * whose business function/product category ARE set (spec 0044,
     * mirrors OpportunityFromLeadTest's completeLead()), an operator, and a
     * site. `source_id` is the LEAD's own (the campaign no longer carries a
     * source; the campaign-source fallback was removed). Returns both the
     * payload and the models tests assert against.
     *
     * @return array{payload: array<string, mixed>, registry: Registry, source: Source, businessFunction: BusinessFunction, productCategory: ProductCategory, operator: User, site: OperationalSite}
     */
    function convertibleLeadFixture(): array
    {
        $registry = Registry::factory()->create();
        $source = Source::factory()->create();
        $businessFunction = BusinessFunction::factory()->create();
        $productCategory = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);
        $campaign = Campaign::factory()->create([
            'business_function_id' => $businessFunction->id,
            'product_category_id' => $productCategory->id,
        ]);
        $operator = User::factory()->create();
        $site = OperationalSite::factory()->create();

        return [
            'payload' => [
                'registry_id' => $registry->id,
                'source_id' => $source->id,
                'campaign_id' => $campaign->id,
                'operator_id' => $operator->id,
                'operational_site_id' => $site->id,
                'convert_to_opportunity' => true,
            ],
            'registry' => $registry,
            'source' => $source,
            'businessFunction' => $businessFunction,
            'productCategory' => $productCategory,
            'operator' => $operator,
            'site' => $site,
        ];
    }
}

// ---------------------------------------------------------------------------
// A) Contextual conversion — happy path (AC-001..AC-007)
// ---------------------------------------------------------------------------

it('AC-001: creates exactly one Opportunity linked to the new lead', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    expect(Opportunity::where('lead_id', $response->json('data.id'))->count())->toBe(1);
});

it('AC-002: the created Opportunity has the lead.operator as its first Gestore Account, and an empty supervisor', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $opportunity->load('managers');

    // Directive 2026-07-21: the Operator becomes the first "Gestore Account"
    // (position 1), not the Supervisor — which stays empty.
    expect($opportunity->supervisor_id)->toBeNull();
    expect($opportunity->managers)->toHaveCount(1);
    expect($opportunity->managers->first()->id)->toBe($fixture['operator']->id);
    expect($opportunity->managers->first()->pivot->position)->toBe(1);
});

it('AC-003: the created Opportunity has registry_id/source_id derived from the lead', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    expect($opportunity->registry_id)->toBe($fixture['registry']->id);
    expect($opportunity->source_id)->toBe($fixture['source']->id);
});

it('AC-004: the created Opportunity has exactly one product line matching the derived pair', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $opportunity->load('productLines');

    expect($opportunity->productLines)->toHaveCount(1);
    expect($opportunity->productLines->first()->business_function_id)->toBe($fixture['businessFunction']->id);
    expect($opportunity->productLines->first()->product_category_id)->toBe($fixture['productCategory']->id);
});

it('AC-005: the created Opportunity name equals the derived product category name', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    expect($opportunity->name)->toBe($fixture['productCategory']->name);
});

it('AC-006: the created Opportunity has the system "new" opportunity_status_id', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $newStatusId = OpportunityStatus::query()->where('system_key', StatusSystemKey::New->value)->value('id');

    expect($opportunity->opportunity_status_id)->toBe($newStatusId);
});

it('AC-007: the response exposes data.opportunity {id,name} and lead_status converted_to_opportunity', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $fixture['payload'])->assertCreated();

    expect($response->json('data.opportunity.id'))->not->toBeNull();
    expect($response->json('data.opportunity.name'))->toBe($fixture['productCategory']->name);
    $response->assertJsonPath('data.lead_status', 'converted_to_opportunity');
});

// ---------------------------------------------------------------------------
// B) Sede/Operatore optional on conversion + rollback (AC-008..AC-012)
//
// Directive 2026-07-21 relaxed spec 0044 AC-008/009: Sede (operational_site_id)
// and Operatore (operator_id) are NO LONGER required when converting. A Lead
// converts without them — the derived Opportunity inherits a null supervisor.
// ---------------------------------------------------------------------------

it('AC-008: missing operator_id with convert_to_opportunity -> 201, opportunity created with no supervisor and no manager', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    $payload = $fixture['payload'];
    unset($payload['operator_id']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $payload)->assertCreated();

    $opportunity = Opportunity::where('lead_id', $response->json('data.id'))->firstOrFail();
    $opportunity->load('managers');

    // No Operator on the lead -> no first Gestore Account seeded, empty supervisor.
    expect($opportunity->supervisor_id)->toBeNull();
    expect($opportunity->managers)->toHaveCount(0);
});

it('AC-009: missing operational_site_id with convert_to_opportunity -> 201, opportunity created', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $fixture = convertibleLeadFixture();
    $payload = $fixture['payload'];
    unset($payload['operational_site_id']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', $payload)->assertCreated();

    expect(Opportunity::where('lead_id', $response->json('data.id'))->count())->toBe(1);
    expect(Lead::find($response->json('data.id'))->state_id)->toBeNull();
});

it('AC-010: convert_to_opportunity absent keeps the legacy behavior, no opportunity created', function () {
    $actor = leadConversionActor(['create'], []);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
    ])->assertCreated();

    expect(Lead::count())->toBe(1);
    expect(Opportunity::count())->toBe(0);
    $response->assertJsonPath('data.opportunity', null);
});

it('AC-011: convert_to_opportunity without opportunities.create -> 403, no lead created', function () {
    $actor = leadConversionActor(['create'], []);
    $fixture = convertibleLeadFixture();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', $fixture['payload'])->assertForbidden();

    expect(Lead::count())->toBe(0);
});

it('AC-012: a campaign with no business function/product category -> 422, transaction rolled back', function () {
    $actor = leadConversionActor(['create'], ['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create([
        'business_function_id' => null,
        'product_category_id' => null,
    ]);
    $operator = User::factory()->create();
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $leadCountBefore = Lead::count();

    $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'operator_id' => $operator->id,
        'operational_site_id' => $site->id,
        'convert_to_opportunity' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Lead::count())->toBe($leadCountBefore);
    expect(Opportunity::count())->toBe(0);
});
