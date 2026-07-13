<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectStatus;
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
 * The 4 BR-2 classification fields, required for a standalone campaign.
 *
 * @return array<string, int>
 */
if (! function_exists('standaloneClassificationFields')) {
    function standaloneClassificationFields(): array
    {
        return [
            'project_status_id' => ProjectStatus::factory()->create()->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'state_id' => State::factory()->create()->id,
            'product_category_id' => ProductCategory::factory()->create()->id,
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-020/AC-022 — linked campaign: the 4 derived columns stay NULL in DB
// ---------------------------------------------------------------------------

it('create: linked to a project, without the 4 derived fields -> 201, DB columns NULL (AC-020)', function () {
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
        'project_status_id' => null,
        'business_function_id' => null,
        'state_id' => null,
        'product_category_id' => null,
    ]);
});

it('create: linked to a project AND an explicit project_status_id -> 422 (AC-022, BR-2)', function () {
    $actor = campaignUserWith(['create']);
    $project = Project::factory()->create();
    $status = ProjectStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', [
        'name' => 'Conflicting',
        'project_id' => $project->id,
        'project_status_id' => $status->id,
    ])->assertStatus(422)->assertJsonValidationErrors('project_status_id');

    expect(Campaign::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-021 — GET linked campaign: derived_from_project + effective project values
// ---------------------------------------------------------------------------

it('show: a linked campaign reports derived_from_project=true with the PROJECT\'s values (AC-021)', function () {
    $actor = campaignUserWith(['view']);
    $status = ProjectStatus::factory()->create(['name' => 'Active', 'color' => '#00ff00']);
    $businessFunction = BusinessFunction::factory()->create(['name' => 'Sales']);
    $state = State::factory()->create(['name' => 'Lazio']);
    $category = ProductCategory::factory()->create(['name' => 'Widgets']);
    $project = Project::factory()->create([
        'project_status_id' => $status->id,
        'business_function_id' => $businessFunction->id,
        'state_id' => $state->id,
        'product_category_id' => $category->id,
    ]);
    $campaign = Campaign::factory()->forProject($project)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/campaigns/{$campaign->id}")
        ->assertOk()
        ->assertJsonPath('data.derived_from_project', true)
        ->assertJsonPath('data.project_status_id', $status->id)
        ->assertJsonPath('data.project_status.name', 'Active')
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
// AC-023 — standalone campaign: the 4 fields are required
// ---------------------------------------------------------------------------

it('create: standalone (project_id null) missing one of the 4 derived fields -> 422 on that field (AC-023)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneClassificationFields();
    unset($fields['state_id']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'Incomplete Standalone'], $fields))
        ->assertStatus(422)->assertJsonValidationErrors('state_id');

    expect(Campaign::count())->toBe(0);
});

it('create: standalone with all 4 derived fields -> 201, derived_from_project=false (AC-023)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneClassificationFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge(['name' => 'Full Standalone'], $fields))
        ->assertCreated()
        ->assertJsonPath('data.derived_from_project', false)
        ->assertJsonPath('data.project_status_id', $fields['project_status_id']);
});

it('create: 403 without campaigns.create', function () {
    $actor = campaignUserWith([]);
    $fields = standaloneClassificationFields();
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
// AC-028 — update: standalone -> linked nulls the 4 derived columns in DB
// ---------------------------------------------------------------------------

it('update: setting project_id on a standalone campaign zeroes the 4 derived columns in DB (AC-028)', function () {
    $actor = campaignUserWith(['update']);
    $fields = standaloneClassificationFields();
    $campaign = Campaign::factory()->create(array_merge(['name' => 'Was Standalone'], $fields));
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$campaign->id}", ['project_id' => $project->id])
        ->assertOk()
        ->assertJsonPath('data.derived_from_project', true);

    $this->assertDatabaseHas('campaigns', [
        'id' => $campaign->id,
        'project_id' => $project->id,
        'project_status_id' => null,
        'business_function_id' => null,
        'state_id' => null,
        'product_category_id' => null,
    ]);
});

it('update: 403 without campaigns.update', function () {
    $actor = campaignUserWith([]);
    $target = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/campaigns/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-029 — code is server-generated, an explicit `code` is ignored
// ---------------------------------------------------------------------------

it('create: code is server-generated CMP-0001, an explicit `code` payload is ignored (AC-029)', function () {
    $actor = campaignUserWith(['create']);
    $fields = standaloneClassificationFields();
    Sanctum::actingAs($actor);

    $this->postJson('/api/campaigns', array_merge([
        'name' => 'Coded',
        'code' => 'HACKED-0001',
    ], $fields))
        ->assertCreated()
        ->assertJsonPath('data.code', 'CMP-0001');

    $this->assertDatabaseMissing('campaigns', ['code' => 'HACKED-0001']);
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
