<?php

use App\Models\Campaign;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

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

// ---------------------------------------------------------------------------
// create — POST /api/projects (AC-010/AC-011/AC-012/AC-013)
// ---------------------------------------------------------------------------

it('create: code is server-generated PRJ-0001, then PRJ-0002 (AC-010)', function () {
    $actor = projectUserWith(['create']);
    $status = ProjectStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['name' => 'First', 'project_status_id' => $status->id])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRJ-0001');

    $this->postJson('/api/projects', ['name' => 'Second', 'project_status_id' => $status->id])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRJ-0002');
});

it('create: an explicit `code` in the payload is ignored (AC-011)', function () {
    $actor = projectUserWith(['create']);
    $status = ProjectStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Spoofed',
        'project_status_id' => $status->id,
        'code' => 'HACKED-9999',
    ])->assertCreated()->assertJsonPath('data.code', 'PRJ-0001');

    $this->assertDatabaseMissing('projects', ['code' => 'HACKED-9999']);
});

it('create: 422 when name is missing (AC-012)', function () {
    $actor = projectUserWith(['create']);
    $status = ProjectStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['project_status_id' => $status->id])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when project_status_id is missing (AC-012)', function () {
    $actor = projectUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['name' => 'No Status'])
        ->assertStatus(422)->assertJsonValidationErrors('project_status_id');
});

it('create: 422 when end_date < start_date (AC-013)', function () {
    $actor = projectUserWith(['create']);
    $status = ProjectStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', [
        'name' => 'Bad Dates',
        'project_status_id' => $status->id,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-01',
    ])->assertStatus(422)->assertJsonValidationErrors('end_date');

    expect(Project::count())->toBe(0);
});

it('create: 403 without projects.create, no row persisted', function () {
    $actor = projectUserWith([]);
    $status = ProjectStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/projects', ['name' => 'Nope', 'project_status_id' => $status->id])->assertForbidden();

    expect(Project::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// show — GET /api/projects/{project} (AC-014)
// ---------------------------------------------------------------------------

it('show: exposes allocated_budget and remaining_budget computed from campaigns (AC-014)', function () {
    $actor = projectUserWith(['view']);
    $project = Project::factory()->create(['total_budget' => 1000]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 300]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 500]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.allocated_budget', '800.00')
        ->assertJsonPath('data.remaining_budget', '200.00')
        ->assertJsonPath('data.campaigns_count', 2);
});

it('show: remaining_budget is null when total_budget is null', function () {
    $actor = projectUserWith(['view']);
    $project = Project::factory()->create(['total_budget' => null]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('data.total_budget', null)
        ->assertJsonPath('data.remaining_budget', null)
        ->assertJsonPath('data.allocated_budget', '0.00');
});

it('show: 403 without projects.view', function () {
    $actor = projectUserWith([]);
    $target = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/projects/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent project', function () {
    $actor = projectUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/projects/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/projects/{project} (AC-015)
// ---------------------------------------------------------------------------

it('update: lowering total_budget below the allocated sum is NEVER blocked, remaining goes negative (AC-015)', function () {
    $actor = projectUserWith(['update']);
    $project = Project::factory()->create(['total_budget' => 1000]);
    Campaign::factory()->forProject($project)->create(['total_budget' => 800]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$project->id}", ['total_budget' => 500])
        ->assertOk()
        ->assertJsonPath('data.total_budget', '500.00')
        ->assertJsonPath('data.remaining_budget', '-300.00');

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'total_budget' => 500.00]);
});

it('update: 403 without projects.update', function () {
    $actor = projectUserWith([]);
    $target = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/projects/{project} (AC-016)
// ---------------------------------------------------------------------------

it('delete: 409 when the project has at least one campaign (AC-016)', function () {
    $actor = projectUserWith(['delete']);
    $project = Project::factory()->create();
    Campaign::factory()->forProject($project)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/projects/{$project->id}")->assertStatus(409);

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
});

it('delete: 204 when the project has no campaigns (AC-016)', function () {
    $actor = projectUserWith(['delete']);
    $project = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});

it('delete: 403 without projects.delete', function () {
    $actor = projectUserWith([]);
    $target = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/projects/{$target->id}")->assertForbidden();
});
