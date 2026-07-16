<?php

use App\Models\PipelineStatus;
use App\Models\StatusGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| System-status rules for `pipeline-statuses` (spec 0039, D-2/D-5/D-6)
|--------------------------------------------------------------------------
|
| The 2 mandatory rows ("Nuovo"/"Chiuso") are seeded unconditionally by the
| system-status migration, so every test here reads them back rather than
| creating them (system_key is UNIQUE — a second 'new'/'closed' row would
| violate it).
*/

if (! function_exists('pipelineStatusSystemUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function pipelineStatusSystemUserWith(array $abilities): User
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
// AC-003 — delete guard on system rows (single), custom rows unaffected
// ---------------------------------------------------------------------------

it('delete: 422 on the system "new" row, message names it, row persists (AC-003)', function () {
    $actor = pipelineStatusSystemUserWith(['delete']);
    $newStatus = PipelineStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/pipeline-statuses/{$newStatus->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', "The '{$newStatus->name}' status is a system status and cannot be deleted.");

    $this->assertDatabaseHas('pipeline_statuses', ['id' => $newStatus->id]);
});

it('delete: 422 on the system "closed" row (AC-003)', function () {
    $actor = pipelineStatusSystemUserWith(['delete']);
    $closedStatus = PipelineStatus::where('system_key', 'closed')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/pipeline-statuses/{$closedStatus->id}")->assertStatus(422);

    $this->assertDatabaseHas('pipeline_statuses', ['id' => $closedStatus->id]);
});

it('delete: a custom, unreferenced row still returns 204 (AC-003, invariant)', function () {
    $actor = pipelineStatusSystemUserWith(['delete']);
    $custom = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/pipeline-statuses/{$custom->id}")->assertNoContent();
});

// ---------------------------------------------------------------------------
// AC-003 — delete guard via the generic bulk-delete endpoint (table framework)
// ---------------------------------------------------------------------------

it('bulk-delete: a system row is rejected, a custom unreferenced row succeeds (AC-003)', function () {
    $actor = pipelineStatusSystemUserWith(['delete', 'viewAny']);
    $newStatus = PipelineStatus::where('system_key', 'new')->firstOrFail();
    $custom = PipelineStatus::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/pipeline-statuses/bulk-delete', ['ids' => [$newStatus->id, $custom->id]])
        ->assertOk();

    expect($response->json('data.deleted'))->toBe(1)
        ->and(collect($response->json('data.failed'))->pluck('id'))->toContain($newStatus->id)
        ->and(collect($response->json('data.failed'))->firstWhere('id', $newStatus->id)['reason'])->toBe('guarded');
});

// ---------------------------------------------------------------------------
// AC-004 — update guard: name/color allowed, status_group_id rejected
// ---------------------------------------------------------------------------

it('update: 200 when a system row changes ONLY name/color (AC-004)', function () {
    $actor = pipelineStatusSystemUserWith(['update']);
    $newStatus = PipelineStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/pipeline-statuses/{$newStatus->id}", ['name' => 'Nuovissimo', 'color' => 'teal'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nuovissimo')
        ->assertJsonPath('data.color', 'teal')
        ->assertJsonPath('data.system_key', 'new');

    $this->assertDatabaseHas('pipeline_statuses', ['id' => $newStatus->id, 'name' => 'Nuovissimo', 'color' => 'teal']);
});

it('update: 422 when a system row payload includes status_group_id, nothing persists (AC-004)', function () {
    $actor = pipelineStatusSystemUserWith(['update']);
    $newStatus = PipelineStatus::where('system_key', 'new')->firstOrFail();
    // "Nuovo" already carries its D-2 FIXED group ("Aperto", assigned at
    // migration time) — the assertion below checks it stays untouched, not
    // that it is null.
    $originalGroupId = $newStatus->status_group_id;
    $group = StatusGroup::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/pipeline-statuses/{$newStatus->id}", ['status_group_id' => $group->id])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    $this->assertDatabaseHas('pipeline_statuses', ['id' => $newStatus->id, 'status_group_id' => $originalGroupId]);
});

it('update: a custom row accepts status_group_id (AC-008)', function () {
    $actor = pipelineStatusSystemUserWith(['update']);
    $custom = PipelineStatus::factory()->create();
    $group = StatusGroup::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/pipeline-statuses/{$custom->id}", ['status_group_id' => $group->id])
        ->assertOk()
        ->assertJsonPath('data.status_group.id', $group->id)
        ->assertJsonPath('data.status_group.name', $group->name);
});

// ---------------------------------------------------------------------------
// AC-008 — status_group_id on create + invalid id + mapRow exposure
// ---------------------------------------------------------------------------

it('create: a custom row with a valid status_group_id returns status_group {id, name, color} (AC-008)', function () {
    $actor = pipelineStatusSystemUserWith(['create']);
    $group = StatusGroup::factory()->create(['color' => 'purple']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/pipeline-statuses', ['name' => 'Attesa', 'status_group_id' => $group->id])
        ->assertCreated()
        ->assertJsonPath('data.status_group_id', $group->id)
        ->assertJsonPath('data.status_group.color', 'purple');
});

it('create: 422 when status_group_id does not exist (AC-008)', function () {
    $actor = pipelineStatusSystemUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/pipeline-statuses', ['name' => 'Attesa', 'status_group_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('status_group_id');
});

it('table rows: mapRow exposes system_key and status_group (AC-008)', function () {
    $actor = pipelineStatusSystemUserWith(['viewAny']);
    $group = StatusGroup::factory()->create();
    PipelineStatus::factory()->withGroup($group->id)->create(['name' => 'Con Gruppo']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/pipeline-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $newRow = collect($response->json('items'))->firstWhere('system_key', 'new');
    expect($newRow)->not->toBeNull()
        ->and($newRow['actions'])->not->toContain('delete');

    $groupedRow = collect($response->json('items'))->firstWhere('name', 'Con Gruppo');
    expect($groupedRow['status_group']['id'])->toBe($group->id);
});

// ---------------------------------------------------------------------------
// AC-006 — automatic placement of a new custom row (before "Chiuso")
// ---------------------------------------------------------------------------

it('create: a new custom row is placed last among customs, before "Chiuso" (AC-006)', function () {
    $actor = pipelineStatusSystemUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/pipeline-statuses', ['name' => 'Primo Custom'])->assertCreated();

    $ordered = PipelineStatus::query()->orderBy('sort_order')->pluck('system_key', 'name');

    expect($ordered->keys()->last())->toBe('Chiuso')
        ->and($ordered->keys()->first())->toBe('Nuovo');
});
