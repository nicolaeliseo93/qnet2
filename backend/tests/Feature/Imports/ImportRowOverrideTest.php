<?php

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0045 — per-row Operator override (PATCH .../rows/{row}), extended to
 * mirror it for the Operational Site override. Split out of
 * ImportOpportunityConversionTest (engineering.md §6, 500-line hard limit) —
 * that file keeps the confirm-gate/conversion_readiness coverage.
 */

/**
 * @param  array<int, string>  $abilities
 */
function overrideActor(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    if (in_array('import', $abilities, true)) {
        grantImportRunsPermissions($user, ['update']);
    }

    return $user;
}

// ---------------------------------------------------------------------------
// Per-row Operator override — PATCH /api/imports/leads/{importRun}/rows/{row}
// ---------------------------------------------------------------------------

it('sets the per-row operator override without touching status/mapped_values', function () {
    $actor = overrideActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'mapped_values' => ['first_name' => 'Mario', 'last_name' => 'Rossi'],
        'status' => ImportRowStatus::Valid,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['operator_id' => $operator->id])
        ->assertOk()
        ->assertJsonPath('data.row.operator_id', $operator->id)
        ->assertJsonPath('data.row.operator.id', $operator->id)
        ->assertJsonPath('data.row.operator.name', $operator->name)
        ->assertJsonPath('data.row.is_edited', true)
        ->assertJsonPath('data.row.status', 'valid');

    $row->refresh();
    expect($row->operator_id)->toBe($operator->id)
        ->and($row->mapped_values)->toBe(['first_name' => 'Mario', 'last_name' => 'Rossi']);
});

it('clears the per-row operator override with an explicit null', function () {
    $actor = overrideActor(['import']);
    $operator = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'operator_id' => $operator->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['operator_id' => null])
        ->assertOk()
        ->assertJsonPath('data.row.operator_id', null)
        ->assertJsonPath('data.row.operator', null);

    expect($row->fresh()->operator_id)->toBeNull();
});

it('403 without leads.import on the per-row operator override', function () {
    $actor = overrideActor([]);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['operator_id' => User::factory()->create()->id])
        ->assertForbidden();
});

it('422 when none of values/geo/operator_id/operational_site_id is submitted', function () {
    $actor = overrideActor(['import']);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [])
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Per-row Operational Site override — mirrors the Operator override above
// ---------------------------------------------------------------------------

it('sets the per-row operational site override without touching status/mapped_values', function () {
    $actor = overrideActor(['import']);
    $site = OperationalSite::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'mapped_values' => ['first_name' => 'Mario', 'last_name' => 'Rossi'],
        'status' => ImportRowStatus::Valid,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['operational_site_id' => $site->id])
        ->assertOk()
        ->assertJsonPath('data.row.operational_site_id', $site->id)
        ->assertJsonPath('data.row.operational_site.id', $site->id)
        ->assertJsonPath('data.row.is_edited', true)
        ->assertJsonPath('data.row.status', 'valid');

    $row->refresh();
    expect($row->operational_site_id)->toBe($site->id)
        ->and($row->mapped_values)->toBe(['first_name' => 'Mario', 'last_name' => 'Rossi']);
});

it('clears the per-row operational site override with an explicit null', function () {
    $actor = overrideActor(['import']);
    $site = OperationalSite::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id, 'operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['operational_site_id' => null])
        ->assertOk()
        ->assertJsonPath('data.row.operational_site_id', null)
        ->assertJsonPath('data.row.operational_site', null);

    expect($row->fresh()->operational_site_id)->toBeNull();
});

it('403 without leads.import on the per-row operational site override', function () {
    $actor = overrideActor([]);
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", ['operational_site_id' => OperationalSite::factory()->create()->id])
        ->assertForbidden();
});
