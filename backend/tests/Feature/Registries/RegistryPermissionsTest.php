<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-018 — permissions:sync + super-admin bypass + navigation node
// ---------------------------------------------------------------------------

it('permissions:sync creates the full registries.* CRUD catalogue', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::whereIn('name', [
        'registries.viewAny', 'registries.view', 'registries.create',
        'registries.update', 'registries.delete', 'registries.export', 'registries.import',
    ])->count())->toBe(7);
});

it('a super-admin actor bypasses registries.* checks via Gate::before', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    Role::findOrCreate('super-admin');
    $actor = User::factory()->create();
    $actor->assignRole('super-admin');

    expect($actor->can('registries.view'))->toBeTrue()
        ->and($actor->can('registries.delete'))->toBeTrue();
});

it('GET /navigation includes the registries node only with registries.view', function () {
    Permission::findOrCreate('registries.view');
    $withoutPermission = User::factory()->create();
    Sanctum::actingAs($withoutPermission);

    $keys = collect($this->getJson('/api/navigation')->assertOk()->json('data'))
        ->pluck('children')->flatten(1)->pluck('key');
    expect($keys)->not->toContain('registries');

    $withPermission = User::factory()->create();
    $withPermission->givePermissionTo('registries.view');
    Sanctum::actingAs($withPermission);

    $keys = collect($this->getJson('/api/navigation')->assertOk()->json('data'))
        ->pluck('children')->flatten(1)->pluck('key');
    expect($keys)->toContain('registries');
});
