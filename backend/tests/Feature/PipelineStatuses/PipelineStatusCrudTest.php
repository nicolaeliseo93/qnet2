<?php

use App\Models\Campaign;
use App\Models\Project;
use App\Models\PipelineStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('pipelineStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function pipelineStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("pipeline-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("pipeline-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create — POST /api/pipeline-statuses (AC-001)
// ---------------------------------------------------------------------------

it('create: 201 + persists (AC-001)', function () {
    $actor = pipelineStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/pipeline-statuses', ['name' => 'Bozza'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bozza');

    $this->assertDatabaseHas('pipeline_statuses', ['name' => 'Bozza']);
});

it('create: 422 when name is missing', function () {
    $actor = pipelineStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/pipeline-statuses', [])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// show — GET /api/pipeline-statuses/{pipelineStatus}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = pipelineStatusUserWith(['view']);
    $target = PipelineStatus::factory()->create(['name' => 'Attivo', 'color' => '#00ff00', 'sort_order' => 3]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/pipeline-statuses/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Attivo')
        ->assertJsonPath('data.color', '#00ff00')
        ->assertJsonPath('data.sort_order', 3);
});

it('show: 404 for a non-existent project status', function () {
    $actor = pipelineStatusUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/pipeline-statuses/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/pipeline-statuses/{pipelineStatus}
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the project status', function () {
    $actor = pipelineStatusUserWith(['update']);
    $target = PipelineStatus::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/pipeline-statuses/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('pipeline_statuses', ['id' => $target->id, 'name' => 'After']);
});

// ---------------------------------------------------------------------------
// AC-002 — 403 without the permission on EVERY verb, no write
// ---------------------------------------------------------------------------

it('GET show: 403 without pipeline-statuses.view (AC-002)', function () {
    $actor = pipelineStatusUserWith([]);
    $target = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/pipeline-statuses/{$target->id}")->assertForbidden();
});

it('POST create: 403 without pipeline-statuses.create, no row created (AC-002)', function () {
    $actor = pipelineStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/pipeline-statuses', ['name' => 'Nope'])->assertForbidden();

    expect(PipelineStatus::count())->toBe(0);
});

it('PATCH update: 403 without pipeline-statuses.update, no change persisted (AC-002)', function () {
    $actor = pipelineStatusUserWith([]);
    $target = PipelineStatus::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/pipeline-statuses/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('pipeline_statuses', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without pipeline-statuses.delete, record still exists (AC-002)', function () {
    $actor = pipelineStatusUserWith([]);
    $target = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/pipeline-statuses/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('pipeline_statuses', ['id' => $target->id]);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/pipeline-statuses/{pipelineStatus} (BR-4, AC-003/004/005)
// ---------------------------------------------------------------------------

it('delete: 409 when referenced by a Project, status still exists (AC-003)', function () {
    $actor = pipelineStatusUserWith(['delete']);
    $target = PipelineStatus::factory()->create();
    Project::factory()->create(['pipeline_status_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/pipeline-statuses/{$target->id}")->assertStatus(409);

    $this->assertDatabaseHas('pipeline_statuses', ['id' => $target->id]);
});

it('delete: 409 when referenced ONLY by a standalone Campaign (project_id null) (AC-004)', function () {
    $actor = pipelineStatusUserWith(['delete']);
    $target = PipelineStatus::factory()->create();
    Campaign::factory()->create(['project_id' => null, 'pipeline_status_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/pipeline-statuses/{$target->id}")->assertStatus(409);

    $this->assertDatabaseHas('pipeline_statuses', ['id' => $target->id]);
});

it('delete: 204 + removed when not referenced by anything (AC-005)', function () {
    $actor = pipelineStatusUserWith(['delete']);
    $target = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/pipeline-statuses/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('pipeline_statuses', ['id' => $target->id]);
});

it('delete: 403 without pipeline-statuses.delete', function () {
    $actor = pipelineStatusUserWith([]);
    $target = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/pipeline-statuses/{$target->id}")->assertForbidden();
});
