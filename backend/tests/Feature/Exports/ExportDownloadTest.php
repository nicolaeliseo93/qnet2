<?php

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\ExportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
// AC-008 — GET /api/exports/{domain}/{exportRun}/download
// ---------------------------------------------------------------------------

it('streams the file with the right Content-Type and attachment filename when completed', function () {
    registerStubExportDomain();
    Storage::fake('local');
    Storage::disk('local')->put('exports/run.csv', "name\nAcme\n");

    $actor = stubExportActorWith(['export']);
    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-exports',
        'status' => ExportStatus::Completed,
        'format' => ExportFormat::Csv,
        'original_filename' => 'stub-exports-2026-07-03.csv',
        'file_path' => 'exports/run.csv',
        'row_count' => 1,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->get("/api/exports/stub-exports/{$run->id}/download")
        ->assertOk();

    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('stub-exports-2026-07-03.csv');
});

it('404 when the run has not completed yet', function () {
    registerStubExportDomain();
    $actor = stubExportActorWith(['export']);
    $run = ExportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->get("/api/exports/stub-exports/{$run->id}/download")->assertNotFound();
});

it('404 when the completed run has no file on disk', function () {
    registerStubExportDomain();
    Storage::fake('local');
    $actor = stubExportActorWith(['export']);
    $run = ExportRun::factory()->completed()->create(['user_id' => $actor->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->get("/api/exports/stub-exports/{$run->id}/download")->assertNotFound();
});

it('403 without business-functions.export', function () {
    registerStubExportDomain();
    Storage::fake('local');
    $actor = stubExportActorWith([]);
    $run = ExportRun::factory()->completed()->create(['user_id' => $actor->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->get("/api/exports/stub-exports/{$run->id}/download")->assertForbidden();
});

it('404 for a run belonging to another user (ownership), never leaking existence', function () {
    registerStubExportDomain();
    Storage::fake('local');
    $actor = stubExportActorWith(['export']);
    $otherUser = User::factory()->create();
    $run = ExportRun::factory()->completed()->create(['user_id' => $otherUser->id, 'resource' => 'stub-exports']);
    Sanctum::actingAs($actor);

    $this->get("/api/exports/stub-exports/{$run->id}/download")->assertNotFound();
});
