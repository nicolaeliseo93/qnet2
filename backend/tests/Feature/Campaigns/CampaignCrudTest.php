<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('campaignUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function campaignUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("campaigns.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("campaigns.{$ability}");
        }

        return $user;
    }
}

/**
 * The 3 BR-2 classification fields, required for a standalone campaign.
 * `state_id` LEFT this group (spec 0027, D-3): it now follows BR-5 like
 * every other geo level, so a standalone campaign only needs `country_id`
 * (see standaloneCampaignFields() below) alongside these three.
 *
 * @return array<string, int>
 */
if (! function_exists('standaloneClassificationFields')) {
    function standaloneClassificationFields(): array
    {
        return [
            'pipeline_status_id' => PipelineStatus::factory()->create()->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => ProductCategory::factory()->create()->id,
        ];
    }
}

/**
 * Every field required to create a valid STANDALONE campaign: the 3 BR-2
 * classification fields plus `country_id` (BR-4, spec 0027).
 *
 * @return array<string, int>
 */
if (! function_exists('standaloneCampaignFields')) {
    function standaloneCampaignFields(): array
    {
        return array_merge(standaloneClassificationFields(), ['country_id' => Country::factory()->create()->id]);
    }
}

// ---------------------------------------------------------------------------
// AC-020/AC-022 — linked campaign: the 4 derived columns stay NULL in DB
// ---------------------------------------------------------------------------

it('create: linked to a project, without the 3 derived fields -> 201, DB columns NULL (AC-020)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/campaigns', [
        'name' => 'Linked Campaign',
        'project_id' => $project->id,
    ])->assertCreated();

    $campaignId = $response->json('data.id');
    $this->assertDatabaseHas('campaigns', [
        'id' => $campaignId,
        'project_id' => $project->id,
        'pipeline_status_id' => null,
        'business_function_id' => null,
        'product_category_id' => null,
        // The project fills country_id by default (ProjectFactory) -> prohibited
        // and NULL on the campaign row (BR-5, spec 0027).
        'country_id' => null,
        'state_id' => null,
        'province_id' => null,
        'city_id' => null,
    ]);
});

it('create: linked to a project AND an explicit pipeline_status_id -> 422 (AC-022, BR-2)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create();
    $status = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Conflicting',
        'project_id' => $project->id,
        'pipeline_status_id' => $status->id,
    ])->assertStatus(422)->assertJsonValidationErrors('pipeline_status_id');

    expect(Campaign::count())->toBe(0);
});

// BR-5 geo refinement (spec 0027, AC-004/AC-005/AC-006) moved to
// CampaignGeoScopeTest.php (file-size split, engineering.md §6).

// ---------------------------------------------------------------------------
// AC-021 — GET linked campaign: derived_from_project + effective project values
// ---------------------------------------------------------------------------

it('show: a linked campaign reports derived_from_project=true with the PROJECT\'s values (AC-021)', function () {
    $actor = campaignUserWith(['view']);
    $status = PipelineStatus::factory()->create(['name' => 'Active', 'color' => '#00ff00']);
    $businessFunction = BusinessFunction::factory()->create(['name' => 'Sales']);
    $state = State::factory()->create(['name' => 'Lazio']);
    $category = ProductCategory::factory()->create(['name' => 'Widgets']);
    $project = Project::factory()->create([
        'pipeline_status_id' => $status->id,
        'business_function_id' => $businessFunction->id,
        'state_id' => $state->id,
        'product_category_id' => $category->id,
    ]);
    $campaign = Campaign::factory()->forProject($project)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/campaigns/{$campaign->id}")
        ->assertOk()
        ->assertJsonPath('data.derived_from_project', true)
        ->assertJsonPath('data.pipeline_status_id', $status->id)
        ->assertJsonPath('data.pipeline_status.name', 'Active')
        ->assertJsonPath('data.business_function_id', $businessFunction->id)
        ->assertJsonPath('data.state_id', $state->id)
        ->assertJsonPath('data.product_category_id', $category->id);
});

it('show: 403 without campaigns.view', function () {
    $actor = campaignUserWith([]);
    $target = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/campaigns/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent campaign', function () {
    $actor = campaignUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/campaigns/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// AC-023 — standalone campaign: the 3 BR-2 fields + country_id are required
// ---------------------------------------------------------------------------

it('create: standalone (project_id null) missing one of the 3 BR-2 fields -> 422 on that field (AC-023)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    unset($fields['business_function_id']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'Incomplete Standalone'], $fields))
        ->assertStatus(422)->assertJsonValidationErrors('business_function_id');

    expect(Campaign::count())->toBe(0);
});

it('create: standalone missing country_id -> 422 on country_id (AC-023, BR-4)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneClassificationFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'No Country'], $fields))
        ->assertStatus(422)->assertJsonValidationErrors('country_id');

    expect(Campaign::count())->toBe(0);
});

it('create: standalone with all 3 BR-2 fields + country_id -> 201, derived_from_project=false (AC-023)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'Full Standalone'], $fields))
        ->assertCreated()
        ->assertJsonPath('data.derived_from_project', false)
        ->assertJsonPath('data.pipeline_status_id', $fields['pipeline_status_id']);
});

it('create: 403 without campaigns.create', function () {
    $actor = campaignUserWith([]);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'Nope'], $fields))->assertForbidden();

    expect(Campaign::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-024/AC-025/AC-027 — BR-3 budget guard on create
// ---------------------------------------------------------------------------

it('create: 422 with an explanatory message when the requested budget exceeds the residual (AC-024)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create(['total_budget' => 1000]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 600]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/campaigns', [
        'name' => 'Over Budget',
        'project_id' => $project->id,
        'total_budget' => 500,
    ])->assertStatus(422)->assertJsonValidationErrors('total_budget');

    $message = collect($response->json('errors.total_budget'))->first();
    expect($message)->toContain($project->code)
        ->and($message)->toContain('1000.00')
        ->and($message)->toContain('600.00')
        ->and($message)->toContain('400.00')
        ->and($message)->toContain('500.00');

    expect(Campaign::where('name', 'Over Budget')->exists())->toBeFalse();
    expect(Campaign::count())->toBe(1); // only the pre-existing one
});

it('create: 201 when the requested budget fits the residual exactly (AC-025)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create(['total_budget' => 1000]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 600]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Within Budget',
        'project_id' => $project->id,
        'total_budget' => 400,
    ])->assertCreated();
});

it('create: any total_budget is accepted when project.total_budget is NULL (AC-027)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create(['total_budget' => null]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Unbounded',
        'project_id' => $project->id,
        'total_budget' => 999999,
    ])->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-026 — update: the campaign's own current budget doesn't double-count
// ---------------------------------------------------------------------------

it('update: the campaign\'s own budget is excluded from its allocated sum (AC-026)', function () {
    $actor = campaignUserWith(['update']);
    $project = Project::factory()->create(['total_budget' => 1000]);
    $campaign = Campaign::factory()->forProject($project)->create(['total_budget' => 600]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['total_budget' => 1000])->assertOk();

    $this->patchJson("/api/campaigns/{$campaign->id}", ['total_budget' => 1001])
        ->assertStatus(422)->assertJsonValidationErrors('total_budget');
});

// ---------------------------------------------------------------------------
// AC-028 — update: standalone -> linked nulls the 3 BR-2 columns in DB;
// geo now follows BR-5 instead (spec 0027, D-3 — this is NOT the former
// blanket "4 derived columns" behaviour, rewritten because the requirement
// changed, not test tampering).
// ---------------------------------------------------------------------------

it('update: setting project_id on a standalone campaign zeroes the 3 BR-2 columns in DB, geo follows BR-5 (AC-028)', function () {
    $actor = campaignUserWith(['update']);
    $fields = standaloneClassificationFields();
    // state_id explicitly null: the campaign's OWN country (its default
    // factory geo) has no state, so linking to a project with a DIFFERENT
    // country never produces an inconsistent merged tuple below.
    $campaign = Campaign::factory()->create(array_merge(['name' => 'Was Standalone', 'state_id' => null], $fields));
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['project_id' => $project->id])
        ->assertOk()
        ->assertJsonPath('data.derived_from_project', true);

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'project_id' => $project->id,
        'pipeline_status_id' => null,
        'business_function_id' => null,
        'product_category_id' => null,
        // The project fills country_id by default (ProjectFactory) -> nulled
        // on the campaign row, defence in depth (BR-5).
        'country_id' => null,
    ]);
});

it('update: 403 without campaigns.update', function () {
    $actor = campaignUserWith([]);
    $target = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// spec 0025 (AC-001..AC-009 for campaigns, mirroring projects) — `code`
// manual-on-create, replacing the former AC-029 "explicit code is ignored".
// ---------------------------------------------------------------------------

it('create: no `code` in the payload -> code is server-generated CMP-0001 (AC-001/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'No Code'], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'CMP-0001');
});

it('create: an explicit `code` in the payload is persisted as-is (AC-002/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge([
        'name' => 'Manual Code',
        'code' => 'ACME-CMP-2026',
    ], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'ACME-CMP-2026');

    $this->assertDatabaseHas('campaigns', ['code' => 'ACME-CMP-2026']);
});

it('create: `code` as an empty string -> code is server-generated (AC-003/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'Empty Code', 'code' => ''], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'CMP-0001');
});

it('create: a duplicate `code` -> 422 on the `code` field (AC-004/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Campaign::factory()->create(['code' => 'ACME-CMP-2026']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge([
        'name' => 'Duplicate Code',
        'code' => 'ACME-CMP-2026',
    ], $fields))
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

it('create: a `code` of 33+ characters -> 422 (AC-005/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge([
        'name' => 'Too Long',
        'code' => str_repeat('A', 33),
    ], $fields))
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

it('create: a manual non-CMP code does not break the sequential generator (AC-006/AC-008)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneCampaignFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge([
        'name' => 'Manual First',
        'code' => 'ACME-CMP-2026',
    ], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'ACME-CMP-2026');

    $this->postJson('/api/campaigns', array_merge(['name' => 'Generated Second'], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'CMP-0001');
});

it('update: a `code` different from the persisted one -> 422, code unchanged (AC-007/AC-008)', function () {
    $actor = campaignUserWith(['update']);
    $campaign = Campaign::factory()->create(['code' => 'CMP-0001']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['code' => 'CMP-9999'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('campaigns', ['id' => $campaign->id, 'code' => 'CMP-0001']);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/campaigns/{campaign} (no delete-guard, unlike Projects)
// ---------------------------------------------------------------------------

it('delete: 204, removes the campaign', function () {
    $actor = campaignUserWith(['delete']);
    $target = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/campaigns/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('campaigns', ['id' => $target->id]);
});

it('delete: 403 without campaigns.delete', function () {
    $actor = campaignUserWith([]);
    $target = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/campaigns/{$target->id}")->assertForbidden();
});
