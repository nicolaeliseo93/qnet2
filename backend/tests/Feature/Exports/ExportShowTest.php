<?php

use App\Enums\ExportStatus;
use App\Models\ExportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\StubExportTableDefinition;

uses(RefreshDatabase::class);

if (! function_exists('stubExportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function stubExportActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('registerStubExportDomain')) {
    function registerStubExportDomain(): void
    {
        config(['tables.definitions' => ['stub-exports' => StubExportTableDefinition::class]]);
    }
}

// ---------------------------------------------------------------------------
// Polling — GET /api/exports/{domain}/{exportRun}
// ---------------------------------------------------------------------------

it('200 with the current status while processing', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    $run = ExportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/exports/stub-exports/{$run->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.export_run.id', $run->id)
        ->assertJsonPath('data.export_run.status', 'processing')
        ->assertJsonPath('data.export_run.has_file', false);
});

it('200 with has_file=true once completed', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    $run = ExportRun::factory()->completed()->create(['user_id' => $actor->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/exports/stub-exports/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.export_run.status', 'completed')
        ->assertJsonPath('data.export_run.row_count', 3)
        ->assertJsonPath('data.export_run.has_file', true);
});

it('never leaks the raw file_path', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    $run = ExportRun::factory()->completed()->create(['user_id' => $actor->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/exports/stub-exports/{$run->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.export_run.file_path');
});

// ---------------------------------------------------------------------------
// AC-002 — 403 without the export ability
// ---------------------------------------------------------------------------

it('403 without business-functions.export', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith([]);
    $run = ExportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/exports/stub-exports/{$run->id}")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-003 — 404 (never 403) for cross-user / cross-domain / unknown runs
// ---------------------------------------------------------------------------

it('404 for a run belonging to another user (ownership)', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    $otherUser = User::factory()->create();
    $run = ExportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/exports/stub-exports/{$run->id}")->assertNotFound();
});

it('404 for a run whose resource does not match the route domain', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    $run = ExportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'some-other-domain']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/exports/stub-exports/{$run->id}")->assertNotFound();
});

it('404 for a non-existent export run', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/exports/stub-exports/999999')->assertNotFound();
});

it('404 for an unregistered domain', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    $run = ExportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/exports/unknown-domain/{$run->id}")->assertNotFound();
});

it('failed status is reported, never stuck in processing', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    $run = ExportRun::factory()->failed()->create(['user_id' => $actor->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/exports/stub-exports/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.export_run.status', 'failed');

    expect(ExportRun::query()->where('status', ExportStatus::Processing)->count())->toBe(0);
});
