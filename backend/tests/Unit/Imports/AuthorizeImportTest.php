<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\StubImportDefinition;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-003 — AbstractImportDefinition::authorizeImport, fail-closed via Gate
// ---------------------------------------------------------------------------

it('denies authorizeImport for an actor without the permission (fail-closed, not fail-open)', function () {
    Permission::findOrCreate('business-functions.import');
    $actor = User::factory()->create();

    $definition = app(StubImportDefinition::class);

    expect($definition->authorizeImport($actor))->toBeFalse();
});

it('grants authorizeImport once the actor has {resource}.import', function () {
    Permission::findOrCreate('business-functions.import');
    $actor = User::factory()->create();
    $actor->givePermissionTo('business-functions.import');

    $definition = app(StubImportDefinition::class);

    expect($definition->authorizeImport($actor))->toBeTrue();
});

it('is fail-closed even when NO permission is registered at all for the model', function () {
    // No Permission::findOrCreate() call: the permission simply does not exist.
    $actor = User::factory()->create();

    $definition = app(StubImportDefinition::class);

    expect($definition->authorizeImport($actor))->toBeFalse();
});
