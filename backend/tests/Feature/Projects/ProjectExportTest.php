<?php

use App\Jobs\GenerateExportJob;
use App\Models\ExportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

if (! function_exists('projectExportPayload')) {
    /**
     * @return array<string, mixed>
     */
    function projectExportPayload(): array
    {
        return [
            'format' => 'csv',
            'columns' => [
                ['colId' => 'code', 'header' => 'Code'],
                ['colId' => 'name', 'header' => 'Name'],
            ],
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-033 — POST /api/exports/projects: export run created with permission,
// 403 without it (the `projects` TableDefinition needs no export-specific
// code — spec 0014, free for any registered domain)
// ---------------------------------------------------------------------------

it('201 creates the ExportRun and dispatches GenerateExportJob with projects.export', function () {
    Queue::fake();
    $actor = projectUserWith(['export']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/projects', projectExportPayload())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.export_run.status', 'processing')
        ->assertJsonPath('data.export_run.resource', 'projects')
        ->assertJsonPath('data.export_run.format', 'csv');

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));
    expect($run->user_id)->toBe($actor->id);

    Queue::assertPushed(GenerateExportJob::class);
});

it('403 without projects.export, no ExportRun created', function () {
    Queue::fake();
    $actor = projectUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/projects', projectExportPayload())->assertForbidden();

    expect(ExportRun::count())->toBe(0);
    Queue::assertNotPushed(GenerateExportJob::class);
});
