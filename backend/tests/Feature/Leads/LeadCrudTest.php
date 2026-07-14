<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\OperationalSite;
use App\Models\Referent;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create (AC-010/AC-011/AC-012/AC-016)
// ---------------------------------------------------------------------------

it('create: with referent_id, campaign_id and lead_status_id only -> 201, the other 4 fields are null (AC-010)', function () {
    $actor = leadUserWith(['create']);
    $referent = Referent::factory()->create();
    $campaign = Campaign::factory()->create();
    $status = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'referent_id' => $referent->id,
        'campaign_id' => $campaign->id,
        'lead_status_id' => $status->id,
    ])->assertCreated();

    $leadId = $response->json('data.id');
    $this->assertDatabaseHas('leads', [
        'id' => $leadId,
        'referent_id' => $referent->id,
        'campaign_id' => $campaign->id,
        'operational_site_id' => null,
        'source_id' => null,
        'operator_id' => null,
        'lead_status_id' => $status->id,
        'notes' => null,
    ]);
});

it('create: 201, response shape matches the frozen contract', function () {
    $actor = leadUserWith(['create']);
    $referent = Referent::factory()->create(['name' => 'Ada Contact']);
    $campaign = Campaign::factory()->create(['name' => 'Spring Push']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Roma 1', 'is_primary' => true]);
    $source = Source::factory()->create(['name' => 'Fiera']);
    $operator = User::factory()->create(['name' => 'Marco Operator']);
    $status = LeadStatus::factory()->create(['name' => 'New', 'color' => 'slate']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', [
        'referent_id' => $referent->id,
        'campaign_id' => $campaign->id,
        'operational_site_id' => $site->id,
        'source_id' => $source->id,
        'operator_id' => $operator->id,
        'lead_status_id' => $status->id,
        'notes' => 'Follow up next week',
    ])->assertCreated()
        ->assertJsonPath('data.referent', ['id' => $referent->id, 'name' => 'Ada Contact'])
        ->assertJsonPath('data.campaign.id', $campaign->id)
        ->assertJsonPath('data.campaign.name', 'Spring Push')
        ->assertJsonPath('data.operational_site.label', 'Via Roma 1')
        ->assertJsonPath('data.source', ['id' => $source->id, 'name' => 'Fiera'])
        ->assertJsonPath('data.operator', ['id' => $operator->id, 'name' => 'Marco Operator'])
        ->assertJsonPath('data.lead_status_id', $status->id)
        ->assertJsonPath('data.lead_status', ['id' => $status->id, 'name' => 'New', 'color' => 'slate'])
        ->assertJsonPath('data.notes', 'Follow up next week');
});

it('create: missing referent_id -> 422 on that field, no row created (AC-011)', function () {
    $actor = leadUserWith(['create']);
    $campaign = Campaign::factory()->create();
    $status = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', ['campaign_id' => $campaign->id, 'lead_status_id' => $status->id])
        ->assertStatus(422)->assertJsonValidationErrors('referent_id');

    expect(Lead::count())->toBe(0);
});

it('create: missing campaign_id -> 422 on that field, no row created (AC-011)', function () {
    $actor = leadUserWith(['create']);
    $referent = Referent::factory()->create();
    $status = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', ['referent_id' => $referent->id, 'lead_status_id' => $status->id])
        ->assertStatus(422)->assertJsonValidationErrors('campaign_id');

    expect(Lead::count())->toBe(0);
});

it('create: missing lead_status_id -> 422 on that field, no row created (spec 0029 D-1, AC-011)', function () {
    $actor = leadUserWith(['create']);
    $referent = Referent::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', ['referent_id' => $referent->id, 'campaign_id' => $campaign->id])
        ->assertStatus(422)->assertJsonValidationErrors('lead_status_id');

    expect(Lead::count())->toBe(0);
});

it('create: 403 without leads.create, no row created (AC-012)', function () {
    $actor = leadUserWith([]);
    $referent = Referent::factory()->create();
    $campaign = Campaign::factory()->create();
    $status = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', [
        'referent_id' => $referent->id,
        'campaign_id' => $campaign->id,
        'lead_status_id' => $status->id,
    ])->assertForbidden();

    expect(Lead::count())->toBe(0);
});

it('create: a non-existent campaign_id -> 422 (exists), not 500 (AC-016)', function () {
    $actor = leadUserWith(['create']);
    $referent = Referent::factory()->create();
    $status = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', [
        'referent_id' => $referent->id,
        'campaign_id' => 999999,
        'lead_status_id' => $status->id,
    ])->assertStatus(422)->assertJsonValidationErrors('campaign_id');

    expect(Lead::count())->toBe(0);
});

it('create: a non-existent lead_status_id -> 422 (exists), not 500 (spec 0029 D-1)', function () {
    $actor = leadUserWith(['create']);
    $referent = Referent::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', [
        'referent_id' => $referent->id,
        'campaign_id' => $campaign->id,
        'lead_status_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('lead_status_id');

    expect(Lead::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// update (AC-013)
// ---------------------------------------------------------------------------

it('update: PATCH with only notes -> 200, only notes changes, the 6 FKs stay put (AC-013)', function () {
    $actor = leadUserWith(['update']);
    $lead = Lead::factory()->create(['notes' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$lead->id}", ['notes' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'After');

    $this->assertDatabaseHas('leads', [
        'id' => $lead->id,
        'referent_id' => $lead->referent_id,
        'campaign_id' => $lead->campaign_id,
        'operational_site_id' => $lead->operational_site_id,
        'source_id' => $lead->source_id,
        'operator_id' => $lead->operator_id,
        'lead_status_id' => $lead->lead_status_id,
        'notes' => 'After',
    ]);
});

// ---------------------------------------------------------------------------
// show/delete authz (AC-014/AC-015)
// ---------------------------------------------------------------------------

it('show: 403 without leads.view (AC-014)', function () {
    $actor = leadUserWith([]);
    $target = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent lead', function () {
    $actor = leadUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/leads/999999')->assertNotFound();
});

it('delete: 403 without leads.delete (AC-014)', function () {
    $actor = leadUserWith([]);
    $target = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/leads/{$target->id}")->assertForbidden();
});

it('delete: 204, removes the lead (AC-015)', function () {
    $actor = leadUserWith(['delete']);
    $target = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/leads/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('leads', ['id' => $target->id]);
});

it('delete: 404 for a non-existent lead', function () {
    $actor = leadUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/leads/999999')->assertNotFound();
});
