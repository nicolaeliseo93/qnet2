<?php

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Spec 0044 (AC-030/031/032/033): `GET /api/leads/{lead}/opportunity-defaults`
 * additive prefill of the Supervisor derived from `lead->operator`. Reuses
 * `completeLead()`/`opportunityFromLeadActor()`/`nonDerivableOpportunityFks()`
 * (globally declared, guarded with `function_exists`, in
 * OpportunityFromLeadTest.php — file-size split, engineering.md §6).
 */

// ---------------------------------------------------------------------------
// AC-030/031 — values.supervisor_id / references.supervisor
// ---------------------------------------------------------------------------

it('opportunity-defaults: a lead with an operator prefills supervisor_id/supervisor (AC-030)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $operator = User::factory()->create();
    $lead->update(['operator_id' => $operator->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.values.supervisor_id'))->toBe($operator->id);
    expect($response->json('data.references.supervisor'))->toBe([
        'id' => $operator->id,
        'name' => $operator->name,
    ]);
});

it('opportunity-defaults: a lead without an operator prefills a null supervisor (AC-031)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($lead->operator_id)->toBeNull();
    expect($response->json('data.values.supervisor_id'))->toBeNull();
    expect($response->json('data.references.supervisor'))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-032 — supervisor_id NEVER locked
// ---------------------------------------------------------------------------

it('opportunity-defaults: supervisor_id is never in locked_fields, which stays exactly [source_id, registry_id] (AC-032)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $lead->update(['operator_id' => User::factory()->create()->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/leads/{$lead->id}/opportunity-defaults")->assertOk();

    expect($response->json('data.locked_fields'))->not->toContain('supervisor_id');
    expect($response->json('data.locked_fields'))->toEqualCanonicalizing(['source_id', 'registry_id']);
});

// ---------------------------------------------------------------------------
// AC-033 — the prefill is a suggestion, not a lock: the user-sent value wins
// ---------------------------------------------------------------------------

it('create with lead_id: a supervisor_id different from lead.operator_id is accepted as-is (AC-033)', function () {
    $actor = opportunityFromLeadActor(['create'], ['view']);
    $lead = completeLead();
    $operator = User::factory()->create();
    $lead->update(['operator_id' => $operator->id]);
    $chosenSupervisor = User::factory()->create();
    Sanctum::actingAs($actor);

    $fks = array_merge(nonDerivableOpportunityFks(), ['supervisor_id' => $chosenSupervisor->id]);

    $response = $this->postJson('/api/opportunities', array_merge([
        'name' => 'Overridden supervisor',
        'lead_id' => $lead->id,
    ], $fks))->assertCreated();

    expect($chosenSupervisor->id)->not->toBe($operator->id);
    $this->assertDatabaseHas('opportunities', [
        'id' => $response->json('data.id'),
        'lead_id' => $lead->id,
        'supervisor_id' => $chosenSupervisor->id,
    ]);
});
