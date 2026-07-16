<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $leadsAbilities
 * @param  array<int, string>  $importRunAbilities
 */
function moduleGateLeadsActor(array $leadsAbilities, array $importRunAbilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($leadsAbilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    grantImportRunsPermissions($user, $importRunAbilities);

    return $user;
}

// ---------------------------------------------------------------------------
// AC-004 — reads (index/show/rows/summary/errors) succeed with import-runs.*
// alone, no {resource}.import required.
// ---------------------------------------------------------------------------

it('AC-004: show/index succeed with import-runs.view(Any) alone, WITHOUT leads.import', function () {
    $actor = moduleGateLeadsActor([], ['viewAny', 'view']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}")->assertOk();
    $this->getJson('/api/imports/leads')->assertOk();
});

it('AC-004: show 403 without the module permission, even WITH leads.import', function () {
    $actor = moduleGateLeadsActor(['import'], []);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}")->assertForbidden();
});

it('AC-004: show 404 for a run belonging to another user, even WITH import-runs.view', function () {
    $actor = moduleGateLeadsActor([], ['view']);
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'leads']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/leads/{$run->id}")->assertNotFound();
});

// ---------------------------------------------------------------------------
// AC-005 — writes double-gated: import-runs.create AND the domain's own
// `{resource}.import`, isolated independently on `leads` (the only domain
// registered after the 2026-07-16 legacy-domains removal).
// ---------------------------------------------------------------------------

it('AC-005: leads upload 403 with import-runs.create but WITHOUT leads.import', function () {
    Queue::fake();
    $actor = moduleGateLeadsActor([], ['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/leads', [
        'file' => UploadedFile::fake()->create('leads.csv', 5, 'text/csv'),
    ])->assertForbidden();
});

it('AC-005: leads upload 403 WITH leads.import but WITHOUT import-runs.create', function () {
    Queue::fake();
    $actor = moduleGateLeadsActor(['import'], []);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/leads', [
        'file' => UploadedFile::fake()->create('leads.csv', 5, 'text/csv'),
    ])->assertForbidden();
});

it('AC-005: leads upload 201 with BOTH import-runs.create AND leads.import', function () {
    Queue::fake();
    $actor = moduleGateLeadsActor(['import'], ['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/leads', [
        'file' => UploadedFile::fake()->create('leads.csv', 5, 'text/csv'),
    ])->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-006 — rows/summary readable in reviewing|completed|failed; updateRow
// strictly reviewing-only.
// ---------------------------------------------------------------------------

it('AC-006: rows/summary return 200 on a completed run (read-only detail view)', function () {
    $actor = moduleGateLeadsActor([], ['view']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Completed,
        'column_mapping' => ['Email' => 'email'],
    ]);
    ImportRunRow::factory()->create(['import_run_id' => $run->id, 'mapped_values' => ['email' => 'a@example.com']]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertOk();
    $this->getJson("/api/imports/leads/{$run->id}/summary")->assertOk();
});

it('AC-006: rows/summary return 200 on a failed run (read-only detail view)', function () {
    $actor = moduleGateLeadsActor([], ['view']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Failed]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertOk();
    $this->getJson("/api/imports/leads/{$run->id}/summary")->assertOk();
});

it('AC-006: rows/summary still 422 outside reviewing|completed|failed (e.g. staging)', function () {
    $actor = moduleGateLeadsActor([], ['view']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Staging]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/imports/leads/{$run->id}/rows")->assertStatus(422);
    $this->getJson("/api/imports/leads/{$run->id}/summary")->assertStatus(422);
});

it('AC-006: updateRow stays 422 on a completed run (edit only allowed in reviewing)', function () {
    $actor = moduleGateLeadsActor(['import'], ['update']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Completed]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['values' => ['email' => 'x@example.com']])
        ->assertStatus(422);
});
