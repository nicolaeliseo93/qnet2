<?php

use App\Models\Campaign;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('projectStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function projectStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("project-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("project-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create — POST /api/project-statuses (AC-001)
// ---------------------------------------------------------------------------

it('create: 201 + persists (AC-001)', function () {
    $actor = projectStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/project-statuses', ['name' => 'Bozza'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bozza');

    $this->assertDatabaseHas('project_statuses', ['name' => 'Bozza']);
});

it('create: 422 when name is missing', function () {
    $actor = projectStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/project-statuses', [])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// show — GET /api/project-statuses/{projectStatus}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = projectStatusUserWith(['view']);
    $target = ProjectStatus::factory()->create(['name' => 'Attivo', 'color' => '#00ff00', 'sort_order' => 3]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/project-statuses/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Attivo')
        ->assertJsonPath('data.color', '#00ff00')
        ->assertJsonPath('data.sort_order', 3);
});

it('show: 404 for a non-existent project status', function () {
    $actor = projectStatusUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/project-statuses/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/project-statuses/{projectStatus}
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the project status', function () {
    $actor = projectStatusUserWith(['update']);
    $target = ProjectStatus::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/project-statuses/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('project_statuses', ['id' => $target->id, 'name' => 'After']);
});

// ---------------------------------------------------------------------------
// AC-002 — 403 without the permission on EVERY verb, no write
// ---------------------------------------------------------------------------

it('GET show: 403 without project-statuses.view (AC-002)', function () {
    $actor = projectStatusUserWith([]);
    $target = ProjectStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/project-statuses/{$target->id}")->assertForbidden();
});

it('POST create: 403 without project-statuses.create, no row created (AC-002)', function () {
    $actor = projectStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/project-statuses', ['name' => 'Nope'])->assertForbidden();

    expect(ProjectStatus::count())->toBe(0);
});

it('PATCH update: 403 without project-statuses.update, no change persisted (AC-002)', function () {
    $actor = projectStatusUserWith([]);
    $target = ProjectStatus::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/project-statuses/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('project_statuses', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without project-statuses.delete, record still exists (AC-002)', function () {
    $actor = projectStatusUserWith([]);
    $target = ProjectStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/project-statuses/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('project_statuses', ['id' => $target->id]);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/project-statuses/{projectStatus} (BR-4, AC-003/004/005)
// ---------------------------------------------------------------------------

it('delete: 409 when referenced by a Project, status still exists (AC-003)', function () {
    $actor = projectStatusUserWith(['delete']);
    $target = ProjectStatus::factory()->create();
    Project::factory()->create(['project_status_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/project-statuses/{$target->id}")->assertStatus(409);

    $this->assertDatabaseHas('project_statuses', ['id' => $target->id]);
});

it('delete: 409 when referenced ONLY by a standalone Campaign (project_id null) (AC-004)', function () {
    $actor = projectStatusUserWith(['delete']);
    $target = ProjectStatus::factory()->create();
    Campaign::factory()->create(['project_id' => null, 'project_status_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/project-statuses/{$target->id}")->assertStatus(409);

    $this->assertDatabaseHas('project_statuses', ['id' => $target->id]);
});

it('delete: 204 + removed when not referenced by anything (AC-005)', function () {
    $actor = projectStatusUserWith(['delete']);
    $target = ProjectStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/project-statuses/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('project_statuses', ['id' => $target->id]);
});

it('delete: 403 without project-statuses.delete', function () {
    $actor = projectStatusUserWith([]);
    $target = ProjectStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/project-statuses/{$target->id}")->assertForbidden();
});
