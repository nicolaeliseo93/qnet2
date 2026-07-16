<?php

use App\Models\ImportRun;
use App\Models\User;
use App\Policies\ImportRunPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001/AC-002 — ImportRunPolicy extends BasePolicy: import-runs.* CRUD set,
// `import` dropped, view/delete additionally require ownership.
// ---------------------------------------------------------------------------

it('denies view/delete without the matching import-runs permission, even for an owned run', function () {
    $user = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $user->id, 'resource' => 'leads']);

    expect($user->can('view', $run))->toBeFalse()
        ->and($user->can('delete', $run))->toBeFalse()
        ->and($user->can('viewAny', ImportRun::class))->toBeFalse()
        ->and($user->can('create', ImportRun::class))->toBeFalse();
});

it('grants view/delete once the actor has import-runs.{view,delete} AND owns the run', function () {
    Permission::findOrCreate('import-runs.view');
    Permission::findOrCreate('import-runs.delete');

    $user = User::factory()->create();
    $user->givePermissionTo(['import-runs.view', 'import-runs.delete']);
    $run = ImportRun::factory()->create(['user_id' => $user->id, 'resource' => 'leads']);

    expect($user->can('view', $run))->toBeTrue()
        ->and($user->can('delete', $run))->toBeTrue();
});

it('denies view/delete on a run owned by someone else, even WITH the permission', function () {
    Permission::findOrCreate('import-runs.view');
    Permission::findOrCreate('import-runs.delete');

    $user = User::factory()->create();
    $user->givePermissionTo(['import-runs.view', 'import-runs.delete']);
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'leads']);

    expect($user->can('view', $run))->toBeFalse()
        ->and($user->can('delete', $run))->toBeFalse();
});

it('update/create are permission-only (no ownership check)', function () {
    Permission::findOrCreate('import-runs.update');
    Permission::findOrCreate('import-runs.create');

    $user = User::factory()->create();
    $user->givePermissionTo(['import-runs.update', 'import-runs.create']);
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'leads']);

    expect($user->can('update', $run))->toBeTrue()
        ->and($user->can('create', ImportRun::class))->toBeTrue();
});

it('BasePolicy::abilities() override excludes `import` from the generated permission set', function () {
    expect((new ImportRunPolicy)->permissions())->toBe([
        'import-runs.viewAny',
        'import-runs.view',
        'import-runs.create',
        'import-runs.update',
        'import-runs.delete',
        'import-runs.export',
    ])->not->toContain('import-runs.import');
});
