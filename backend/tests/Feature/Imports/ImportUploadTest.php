<?php

use App\Jobs\ValidateImportJob;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\StubImportDefinition;

uses(RefreshDatabase::class);

if (! function_exists('stubImportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     * @param  array<int, string>  $importRunAbilities  the `import-runs.*` MODULE
     *                                                  abilities (spec 0034), independent of the
     *                                                  domain `business-functions.*` ones above
     */
    function stubImportActorWith(array $abilities, array $importRunAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        grantImportRunsPermissions($user, $importRunAbilities);

        return $user;
    }
}

if (! function_exists('registerStubImportDomain')) {
    function registerStubImportDomain(): void
    {
        config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class]]);
    }
}

// ---------------------------------------------------------------------------
// AC-007 — POST /api/imports/{domain}
// ---------------------------------------------------------------------------

it('201 + creates the ImportRun(status=validating) + stores the file on disk local + dispatches ValidateImportJob', function () {
    registerStubImportDomain();
    Storage::fake('local');
    Queue::fake();
    $actor = stubImportActorWith(['import'], ['create']);
    Sanctum::actingAs($actor);

    $file = UploadedFile::fake()->create('widgets.csv', 10, 'text/csv');

    $response = $this->postJson('/api/imports/stub-widgets', ['file' => $file])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.import_run.status', 'validating')
        ->assertJsonPath('data.import_run.resource', 'stub-widgets')
        ->assertJsonPath('data.import_run.original_filename', 'widgets.csv')
        ->assertJsonPath('data.import_run.total_rows', 0)
        ->assertJsonPath('data.import_run.imported_rows', null)
        ->assertJsonPath('data.import_run.has_error_report', false);

    $runId = $response->json('data.import_run.id');
    $run = ImportRun::findOrFail($runId);

    expect($run->user_id)->toBe($actor->id)
        ->and($run->stored_path)->not->toBeNull();

    Storage::disk('local')->assertExists($run->stored_path);

    Queue::assertPushed(ValidateImportJob::class);
});

it('403 without {resource}.import', function () {
    registerStubImportDomain();
    Queue::fake();
    $actor = stubImportActorWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets', ['file' => UploadedFile::fake()->create('widgets.csv', 10, 'text/csv')])
        ->assertForbidden();

    Queue::assertNotPushed(ValidateImportJob::class);
});

it('404 for an unregistered domain', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/unknown-domain', ['file' => UploadedFile::fake()->create('widgets.csv', 10, 'text/csv')])
        ->assertNotFound();
});

it('422 when the file is missing', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets', [])
        ->assertStatus(422)->assertJsonValidationErrors('file');
});

it('422 when the file type is not csv/txt', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets', [
        'file' => UploadedFile::fake()->create('evil.exe', 10, 'application/x-msdownload'),
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});

it('422 when the file exceeds IMPORT_MAX_FILE_KB', function () {
    registerStubImportDomain();
    config(['imports.max_file_kb' => 5]);
    $actor = stubImportActorWith(['import']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets', [
        'file' => UploadedFile::fake()->create('widgets.csv', 10, 'text/csv'),
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});
