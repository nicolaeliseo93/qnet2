<?php

use App\Models\Campaign;
use App\Models\Project;
use App\Models\ProjectStatus;
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

// ---------------------------------------------------------------------------
// AC-030 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/campaigns/columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = campaignUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/campaigns/columns')->assertForbidden();

    $actor = campaignUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/campaigns/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('campaigns')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['code', 'name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe([
        'code', 'project', 'name', 'registry', 'project_status', 'source',
        'start_date', 'end_date', 'total_budget', 'target_lead', 'created_at',
    ]);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['project']['sortable'])->toBeTrue()
        ->and($columns['project_status']['sortable'])->toBeFalse()
        ->and($columns['project_status']['filterType'])->toBe('set');
});

// ---------------------------------------------------------------------------
// AC-032 — a linked campaign's project_status column shows the PROJECT's
// status (COALESCE read-through), and the set filter finds it
// ---------------------------------------------------------------------------

it('rows: a linked campaign\'s project_status shows the project\'s status (AC-032)', function () {
    $actor = campaignUserWith(['viewAny']);
    $status = ProjectStatus::factory()->create(['name' => 'On Track']);
    $project = Project::factory()->create(['project_status_id' => $status->id]);
    $campaign = Campaign::factory()->forProject($project)->create(['name' => 'Linked Row']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $campaign->id);

    expect($row['project_status'])->toMatchArray(['id' => $status->id, 'name' => 'On Track']);
});

it('rows: the project_status set filter finds a linked campaign via the project\'s status (AC-032)', function () {
    $actor = campaignUserWith(['viewAny']);
    $status = ProjectStatus::factory()->create(['name' => 'Escalated']);
    $otherStatus = ProjectStatus::factory()->create(['name' => 'Closed']);
    $project = Project::factory()->create(['project_status_id' => $status->id]);
    $linked = Campaign::factory()->forProject($project)->create(['name' => 'Matches']);
    $otherProject = Project::factory()->create(['project_status_id' => $otherStatus->id]);
    Campaign::factory()->forProject($otherProject)->create(['name' => 'Does Not Match']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['project_status' => ['filterType' => 'set', 'values' => ['Escalated']]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$linked->id]);
});

it('rows: a standalone campaign\'s project_status shows its OWN status', function () {
    $actor = campaignUserWith(['viewAny']);
    $status = ProjectStatus::factory()->create(['name' => 'Standalone Status']);
    $campaign = Campaign::factory()->create(['name' => 'Standalone Row', 'project_status_id' => $status->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/campaigns/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $campaign->id);

    expect($row['project_status'])->toMatchArray(['id' => $status->id, 'name' => 'Standalone Status']);
});
