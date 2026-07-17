<?php

use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('collects distinct permissions from the navigation config (including nested)', function () {
    config(['navigation.items' => [
        ['key' => 'admin', 'permission' => null, 'children' => [
            ['key' => 'users', 'permission' => 'users.view'],
            ['key' => 'roles', 'permission' => 'roles.view'],
        ]],
        ['key' => 'users-again', 'permission' => 'users.view'], // duplicate
        ['key' => 'dashboard', 'permission' => null],           // no permission
    ]]);

    expect(app(NavigationService::class)->permissions())
        ->toEqualCanonicalizing(['users.view', 'roles.view']);
});

it('creates the navigation permissions via the command', function () {
    config(['navigation.items' => [
        ['key' => 'users', 'permission' => 'users.view'],
        ['key' => 'roles', 'permission' => 'roles.view'],
    ]]);

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::whereIn('name', ['users.view', 'roles.view'])->count())->toBe(2);
});

it('creates the standard CRUD permissions declared by resource policies', function () {
    config(['navigation.items' => []]); // isolate: only policy permissions

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::whereIn('name', [
        'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.delete',
    ])->count())->toBe(5);
});

it('mints no import-runs.* permissions — the import module reuses leads.import', function () {
    config(['navigation.items' => []]); // isolate: only policy permissions

    $this->artisan('permissions:sync')->assertSuccessful();

    // The dedicated `import-runs.*` set was removed (2026-07-17): ImportRunPolicy
    // contributes nothing to the catalog and the whole module rides `leads.import`.
    expect(Permission::where('name', 'like', 'import-runs.%')->exists())->toBeFalse()
        ->and(Permission::where('name', 'leads.import')->exists())->toBeTrue();
});

it('is idempotent and does not duplicate permissions', function () {
    config(['navigation.items' => [
        ['key' => 'users', 'permission' => 'users.view'],
    ]]);

    $this->artisan('permissions:sync')->assertSuccessful();
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'users.view')->count())->toBe(1);
});
