<?php

use App\Jobs\GenerateExportJob;
use App\Models\ExportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

if (! function_exists('importRunsExportPayload')) {
    /**
     * @return array<string, mixed>
     */
    function importRunsExportPayload(): array
    {
        return [
            'format' => 'csv',
            'columns' => [
                ['colId' => 'original_filename', 'header' => 'File'],
                ['colId' => 'status', 'header' => 'Status'],
            ],
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-008 — POST /api/exports/import-runs
// ---------------------------------------------------------------------------

it('201 creates the ExportRun and dispatches GenerateExportJob with import-runs.export', function () {
    Queue::fake();
    $actor = User::factory()->create();
    grantImportRunsPermissions($actor, ['export']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/import-runs', importRunsExportPayload())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.export_run.status', 'processing')
        ->assertJsonPath('data.export_run.resource', 'import-runs')
        ->assertJsonPath('data.export_run.format', 'csv');

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));
    expect($run->user_id)->toBe($actor->id);

    Queue::assertPushed(GenerateExportJob::class);
});

it('403 without import-runs.export, no ExportRun created', function () {
    Queue::fake();
    $actor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/import-runs', importRunsExportPayload())->assertForbidden();

    expect(ExportRun::count())->toBe(0);
    Queue::assertNotPushed(GenerateExportJob::class);
});
