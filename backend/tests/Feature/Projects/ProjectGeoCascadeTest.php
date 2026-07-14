<?php

use App\Models\Campaign;
use App\Models\City;
use App\Models\Country;
use App\Models\Project;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// BR-5 realignment cascade (spec 0027 addendum, committent decision
// 2026-07-14: "the project wins"). Closes a data-integrity gap found by an
// independent verification: two independently-valid PATCHes (a campaign
// refining a level the project left empty, then a LATER project update
// claiming that same level) used to leave the campaign row pointing at a geo
// tuple that was no longer coherent with the project it is locked to.
// UpdateProjectRequest::withValidator() only re-validates the PROJECT's own
// tuple — it has no notion of the campaigns hanging off it. The fix lives in
// ProjectService::update() (via the RealignsCampaignGeo concern).

if (! function_exists('projectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function projectUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("projects.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("projects.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('grantCampaignView')) {
    function grantCampaignView(User $actor): void
    {
        Permission::findOrCreate('campaigns.view');
        $actor->givePermissionTo('campaigns.view');
    }
}

if (! function_exists('toscanaGeo')) {
    /**
     * A SECOND state/city branch under the SAME country as `geoChain()`
     * (Italia): Toscana / Firenze — the region the reported bug's project
     * update reclaims, distinct from geoChain()'s Lombardia / Milano.
     *
     * @return array{state: State, city: City}
     */
    function toscanaGeo(Country $country): array
    {
        $state = State::factory()->create(['name' => 'Toscana', 'country_id' => $country->id]);
        $city = City::factory()->forState($state)->create(['name' => 'Firenze']);

        return compact('state', 'city');
    }
}

// ---------------------------------------------------------------------------
// the reported bug, reproduced verbatim: two valid PATCHes must never leave
// the campaign pointing at a city outside its merged region
// ---------------------------------------------------------------------------

it('update: a project claiming a state the campaign already refined nulls the now-incoherent city too (reproduces the reported bug)', function () {
    $actor = projectUserWith(['create', 'update']);
    $geo = geoChain();
    $toscana = toscanaGeo($geo['country']);

    // Project P: country only (Italia), state/province/city empty.
    $project = Project::factory()->create([
        'country_id' => $geo['country']->id,
        'state_id' => null,
        'province_id' => null,
        'city_id' => null,
    ]);

    // Campaign C, linked: legitimately refines state=Lombardia, city=Milano
    // (the project left both levels empty at the time).
    $campaign = Campaign::factory()->forProject($project)->create([
        'state_id' => $geo['state']->id,
        'city_id' => $geo['city']->id,
    ]);

    Sanctum::actingAs($actor);

    // PATCH project: claims state=Toscana. Valid on the project's OWN tuple
    // (Toscana belongs to Italia) — this is the second, independently-valid
    // request that used to strand the campaign.
    $this->patchJson("/api/projects/{$project->id}", ['state_id' => $toscana['state']->id])
        ->assertOk()
        ->assertJsonPath('data.state_id', $toscana['state']->id);

    // The campaign no longer owns state (reclaimed by the project) NOR the
    // now-incoherent city (Milano does not belong to Toscana).
    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'state_id' => null,
        'city_id' => null,
    ]);
});

// ---------------------------------------------------------------------------
// a finer level that STILL belongs to the new merged tuple survives
// ---------------------------------------------------------------------------

it('update: a campaign city that still belongs to the project\'s new region is left untouched', function () {
    $actor = projectUserWith(['create', 'update']);
    $italy = Country::factory()->create(['name' => 'Italia']);
    $toscana = toscanaGeo($italy);

    $project = Project::factory()->create([
        'country_id' => $italy->id,
        'state_id' => null,
        'province_id' => null,
        'city_id' => null,
    ]);

    // Campaign refines state=Toscana, city=Firenze (a real child of Toscana).
    $campaign = Campaign::factory()->forProject($project)->create([
        'state_id' => $toscana['state']->id,
        'city_id' => $toscana['city']->id,
    ]);

    Sanctum::actingAs($actor);

    // PATCH project: claims the SAME state (Toscana) the campaign already
    // refined to. State is reclaimed (nulled — the project now owns it), but
    // Firenze still belongs to Toscana: it must survive the cascade.
    $this->patchJson("/api/projects/{$project->id}", ['state_id' => $toscana['state']->id])
        ->assertOk();

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'state_id' => null,
        'city_id' => $toscana['city']->id,
    ]);
});

// ---------------------------------------------------------------------------
// the cascade realigns EVERY campaign linked to the project, not just one
// ---------------------------------------------------------------------------

it('update: realigns every campaign linked to the project', function () {
    $actor = projectUserWith(['create', 'update']);
    $geo = geoChain();
    $toscana = toscanaGeo($geo['country']);

    $project = Project::factory()->create([
        'country_id' => $geo['country']->id,
        'state_id' => null,
        'province_id' => null,
        'city_id' => null,
    ]);

    $campaignA = Campaign::factory()->forProject($project)->create([
        'state_id' => $geo['state']->id,
        'city_id' => $geo['city']->id,
    ]);
    $campaignB = Campaign::factory()->forProject($project)->create([
        'state_id' => $geo['state']->id,
        'city_id' => $geo['city']->id,
    ]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['state_id' => $toscana['state']->id])
        ->assertOk();

    $this->assertDatabaseHas('campaigns', ['id' => $campaignA->id, 'state_id' => null, 'city_id' => null]);
    $this->assertDatabaseHas('campaigns', ['id' => $campaignB->id, 'state_id' => null, 'city_id' => null]);
});

// ---------------------------------------------------------------------------
// the project CLEARING a level frees the campaign again, without touching
// unrelated data (no forced null where the campaign owned nothing there)
// ---------------------------------------------------------------------------

it('update: a project clearing a level frees the campaign to refine it again, leaving its other data intact', function () {
    $actor = projectUserWith(['create', 'update']);
    grantCampaignView($actor);
    $geo = geoChain();

    // Project fills country+state (Toscana-equivalent: Lombardia here).
    $project = Project::factory()->create([
        'country_id' => $geo['country']->id,
        'state_id' => $geo['state']->id,
        'province_id' => null,
        'city_id' => null,
    ]);

    // Campaign is fully derived at those two levels (nothing of its own to
    // lose there) but carries its own distinguishing data.
    $campaign = Campaign::factory()->forProject($project)->create([
        'name' => 'Kept Name',
        'total_budget' => 1234.56,
    ]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['state_id' => null])
        ->assertOk()
        ->assertJsonPath('data.state_id', null);

    // Nothing to reclaim/orphan (campaign owned nothing at state/city) — its
    // own attributes are untouched.
    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'name' => 'Kept Name',
        'total_budget' => '1234.56',
        'state_id' => null,
        'city_id' => null,
    ]);

    // Nothing was actually dirty on the campaign row, so no spurious
    // activity entry was written for it.
    expect(
        Activity::query()->where('log_name', 'campaigns')->where('subject_id', $campaign->id)->where('description', 'updated')->exists()
    )->toBeFalse();

    // The campaign is free to refine state again: no longer locked.
    $this->getJson("/api/campaigns/{$campaign->id}")
        ->assertOk()
        ->assertJsonPath('data.geo_locked_levels', ['country']);
});

// ---------------------------------------------------------------------------
// the wipe is auditable: who cleared what, and when
// ---------------------------------------------------------------------------

it('update: the geo cascade wipe is recorded in the campaign activity log', function () {
    $actor = projectUserWith(['create', 'update']);
    $geo = geoChain();
    $toscana = toscanaGeo($geo['country']);

    $project = Project::factory()->create([
        'country_id' => $geo['country']->id,
        'state_id' => null,
        'province_id' => null,
        'city_id' => null,
    ]);

    $campaign = Campaign::factory()->forProject($project)->create([
        'state_id' => $geo['state']->id,
        'city_id' => $geo['city']->id,
    ]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['state_id' => $toscana['state']->id])->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'campaigns')
        ->where('subject_id', $campaign->id)
        ->where('description', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($actor->id)
        ->and($activity->changes()['attributes'])->toMatchArray([
            'state_id' => null,
            'city_id' => null,
        ])
        ->and($activity->changes()['old'])->toMatchArray([
            'state_id' => $geo['state']->id,
            'city_id' => $geo['city']->id,
        ]);
});
