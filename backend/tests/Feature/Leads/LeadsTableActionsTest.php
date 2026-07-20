<?php

use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Deferred conversion row action (spec 0044, AC-020..AC-023): the
 * `convert_to_opportunity` catalogue entry is gated on `opportunities.create`
 * (a cross-resource permission, not any `leads.*` ability), and the per-row
 * whitelist additionally hides it once the lead already carries an
 * Opportunity. Mirrors LeadTableTest's actor-builder style, extended with the
 * `opportunities.create` ability.
 */
if (! function_exists('leadTableActionsActor')) {
    /**
     * @param  array<int, string>  $leadAbilities
     */
    function leadTableActionsActor(array $leadAbilities, bool $canCreateOpportunities): User
    {
        foreach (['viewAny', 'view', 'update', 'delete', 'viewActivity'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }
        Permission::findOrCreate('opportunities.create');

        $user = User::factory()->create();

        foreach ($leadAbilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        if ($canCreateOpportunities) {
            $user->givePermissionTo('opportunities.create');
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-020 / AC-021 — action catalogue, gated on opportunities.create
// ---------------------------------------------------------------------------

it('catalogue includes convert_to_opportunity for an actor with opportunities.create (AC-020)', function () {
    $actor = leadTableActionsActor(['viewAny'], canCreateOpportunities: true);
    Sanctum::actingAs($actor);

    $actions = collect($this->getJson('/api/tables/leads/columns')->assertOk()->json('data.actions'));
    $entry = $actions->firstWhere('key', 'convert_to_opportunity');

    expect($entry)->not->toBeNull()
        ->and($entry)->toMatchArray([
            'key' => 'convert_to_opportunity',
            'label' => 'actions.convertToOpportunity',
            'type' => 'action',
            'confirm' => false,
        ])
        ->and($entry)->not->toHaveKey('permission');
});

it('catalogue omits convert_to_opportunity for an actor without opportunities.create (AC-021)', function () {
    $actor = leadTableActionsActor(['viewAny'], canCreateOpportunities: false);
    Sanctum::actingAs($actor);

    $actionKeys = collect($this->getJson('/api/tables/leads/columns')->assertOk()->json('data.actions'))
        ->pluck('key')->all();

    expect($actionKeys)->not->toContain('convert_to_opportunity');
});

// ---------------------------------------------------------------------------
// AC-022 / AC-023 — per-row whitelist, hidden once converted
// ---------------------------------------------------------------------------

it('row.actions contains convert_to_opportunity for a non-converted lead (AC-022)', function () {
    $actor = leadTableActionsActor(['viewAny'], canCreateOpportunities: true);
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/leads/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $lead->id)['actions'])->toContain('convert_to_opportunity');
});

it('row.actions omits convert_to_opportunity for an already-converted lead (AC-023)', function () {
    $actor = leadTableActionsActor(['viewAny'], canCreateOpportunities: true);
    $lead = Lead::factory()->create();
    Opportunity::factory()->create(['lead_id' => $lead->id]);
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/leads/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $lead->id)['actions'])->not->toContain('convert_to_opportunity');
});

it('row.actions omits convert_to_opportunity for an actor without opportunities.create', function () {
    $actor = leadTableActionsActor(['viewAny'], canCreateOpportunities: false);
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/leads/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $lead->id)['actions'])->not->toContain('convert_to_opportunity');
});
