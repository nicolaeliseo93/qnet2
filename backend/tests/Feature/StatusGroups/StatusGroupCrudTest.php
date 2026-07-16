<?php

use App\Models\LeadStatus;
use App\Models\PipelineStatus;
use App\Models\StatusGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('statusGroupUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function statusGroupUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("status-groups.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("status-groups.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create — POST /api/status-groups (AC-007)
// ---------------------------------------------------------------------------

it('create: 201 + persists (AC-007)', function () {
    $actor = statusGroupUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/status-groups', ['name' => 'In corso', 'color' => 'blue', 'sort_order' => 2])
        ->assertCreated()
        ->assertJsonPath('data.name', 'In corso')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.sort_order', 2);

    $this->assertDatabaseHas('status_groups', ['name' => 'In corso', 'color' => 'blue', 'sort_order' => 2]);
});

it('create: 422 when name is missing', function () {
    $actor = statusGroupUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/status-groups', [])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when name duplicates an existing group, no row created (AC-007)', function () {
    $actor = statusGroupUserWith(['create']);
    StatusGroup::factory()->create(['name' => 'Custom Duplicate']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/status-groups', ['name' => 'Custom Duplicate'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    expect(StatusGroup::where('name', 'Custom Duplicate')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// show — GET /api/status-groups/{statusGroup} (AC-007)
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape (AC-007)', function () {
    $actor = statusGroupUserWith(['view']);
    $target = StatusGroup::factory()->create(['name' => 'Attivo', 'color' => 'blue', 'sort_order' => 3]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/status-groups/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Attivo')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.sort_order', 3);
});

it('show: 404 for a non-existent status group', function () {
    $actor = statusGroupUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/status-groups/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/status-groups/{statusGroup} (AC-007)
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the status group', function () {
    $actor = statusGroupUserWith(['update']);
    $target = StatusGroup::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/status-groups/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('status_groups', ['id' => $target->id, 'name' => 'After']);
});

it('update: 200 when re-submitting its OWN unchanged name (unique ignores self)', function () {
    $actor = statusGroupUserWith(['update']);
    $target = StatusGroup::factory()->create(['name' => 'Same', 'sort_order' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/status-groups/{$target->id}", ['name' => 'Same', 'sort_order' => 5])
        ->assertOk()
        ->assertJsonPath('data.name', 'Same')
        ->assertJsonPath('data.sort_order', 5);
});

it('update: 422 when name duplicates ANOTHER existing group (AC-007)', function () {
    $actor = statusGroupUserWith(['update']);
    StatusGroup::factory()->create(['name' => 'Taken']);
    $target = StatusGroup::factory()->create(['name' => 'Mine']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/status-groups/{$target->id}", ['name' => 'Taken'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// authz — 403 without the permission on EVERY verb, no write
// ---------------------------------------------------------------------------

it('GET show: 403 without status-groups.view (AC-007)', function () {
    $actor = statusGroupUserWith([]);
    $target = StatusGroup::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/status-groups/{$target->id}")->assertForbidden();
});

it('POST create: 403 without status-groups.create, no row created (AC-007)', function () {
    $actor = statusGroupUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/status-groups', ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseMissing('status_groups', ['name' => 'Nope']);
});

it('PATCH update: 403 without status-groups.update, no change persisted (AC-007)', function () {
    $actor = statusGroupUserWith([]);
    $target = StatusGroup::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/status-groups/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('status_groups', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without status-groups.delete, record still exists (AC-007)', function () {
    $actor = statusGroupUserWith([]);
    $target = StatusGroup::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/status-groups/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('status_groups', ['id' => $target->id]);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/status-groups/{statusGroup} (AC-007)
// ---------------------------------------------------------------------------

it('delete: 409 when referenced by a PipelineStatus, group AND status still exist (AC-007)', function () {
    $actor = statusGroupUserWith(['delete']);
    $target = StatusGroup::factory()->create();
    $status = PipelineStatus::factory()->withGroup($target->id)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/status-groups/{$target->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This status group is used by a status and cannot be deleted.');

    $this->assertDatabaseHas('status_groups', ['id' => $target->id]);
    $this->assertDatabaseHas('pipeline_statuses', ['id' => $status->id]);
});

it('delete: 409 when referenced by a LeadStatus (AC-007)', function () {
    $actor = statusGroupUserWith(['delete']);
    $target = StatusGroup::factory()->create();
    LeadStatus::factory()->withGroup($target->id)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/status-groups/{$target->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This status group is used by a status and cannot be deleted.');

    $this->assertDatabaseHas('status_groups', ['id' => $target->id]);
});

it('delete: 204 + removed when not referenced by anything (AC-007)', function () {
    $actor = statusGroupUserWith(['delete']);
    $target = StatusGroup::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/status-groups/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('status_groups', ['id' => $target->id]);
});

it('delete: 403 without status-groups.delete', function () {
    $actor = statusGroupUserWith([]);
    $target = StatusGroup::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/status-groups/{$target->id}")->assertForbidden();
});
