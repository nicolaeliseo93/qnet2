<?php

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * RolesImportDefinition, end-to-end through the real registered `roles`
 * domain (config/imports.php).
 *
 * runValidateImportJob()/runProcessImportJob() are declared (unguarded) in
 * ValidateImportJobTest.php/ProcessImportJobTest.php and are globally
 * available across the Feature/Imports suite.
 */
it('dry-run validates every row (name required / dup existing / dup in-file / unknown permission); commit creates roles with permissions attached', function () {
    Storage::fake('local');
    Permission::findOrCreate('companies.view');
    Permission::findOrCreate('companies.create');
    Role::factory()->create(['name' => 'Support']);

    $header = 'name,permissions';
    $csv = $header."\n"
        .'Editor,companies.view|companies.create'."\n" // valid
        .',companies.view'."\n" // invalid: name required
        .'Editor,companies.view'."\n" // invalid: intra-file duplicate of row 1
        .'Support,'."\n" // invalid: duplicate of an existing DB row
        .'Viewer,companies.view|nonexistent.permission'."\n"; // invalid: unknown permission
    Storage::disk('local')->put('imports/roles.csv', $csv);

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'roles',
        'status' => ImportStatus::Validating,
        'stored_path' => 'imports/roles.csv',
    ]);

    runValidateImportJob($run);

    $validated = $run->fresh();
    expect($validated->status)->toBe(ImportStatus::AwaitingConfirmation)
        ->and($validated->total_rows)->toBe(5)
        ->and($validated->valid_rows)->toBe(1)
        ->and($validated->invalid_rows)->toBe(4)
        ->and(Role::query()->count())->toBe(1); // dry-run created nothing (only pre-existing "Support")

    $reasons = collect($validated->preview['invalid_sample'])->pluck('errors')->flatten()->implode(' ');
    expect($reasons)->toContain('name is required')
        ->and($reasons)->toContain('Duplicate row within the file')
        ->and($reasons)->toContain('already exists')
        ->and($reasons)->toContain('Unknown permission(s): nonexistent.permission');

    $run->update(['status' => ImportStatus::Processing]);
    runProcessImportJob($run);

    $processed = $run->fresh();
    expect($processed->status)->toBe(ImportStatus::Completed)
        ->and($processed->imported_rows)->toBe(1);

    $editor = Role::query()->where('name', 'Editor')->firstOrFail();
    expect($editor->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['companies.create', 'companies.view']);
});
