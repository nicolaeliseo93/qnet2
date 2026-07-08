<?php

use App\Models\EaSector;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('eaSectorUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function eaSectorUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("ea-sectors.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("ea-sectors.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-015 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 ea-sectors.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "ea-sectors.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-015 — navigation node gated by ea-sectors.view
// ---------------------------------------------------------------------------

it('navigation: the ea-sectors node only shows with ea-sectors.view', function () {
    Permission::findOrCreate('ea-sectors.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'configuration'))
        ->not->toContain('ea-sectors');

    $withView = User::factory()->create();
    $withView->givePermissionTo('ea-sectors.view');
    Sanctum::actingAs($withView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'configuration'))
        ->toContain('ea-sectors');
});

// ---------------------------------------------------------------------------
// AC-016 — field permissions
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = eaSectorUserWith([]);
    $target = EaSector::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: EnforcesFieldPermissions rejects a write to parent_id when the role locks it, no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("ea-sectors.{$ability}");
    }

    $role = Role::create(['name' => 'ea-sector-locked']);
    $role->givePermissionTo(['ea-sectors.view', 'ea-sectors.update']);
    $role->fieldPermissions()->create([
        'resource' => 'ea-sectors',
        'field' => 'parent_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $newParent = EaSector::factory()->create();
    $target = EaSector::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$target->id}", ['parent_id' => $newParent->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent_id');

    expect($target->fresh()->parent_id)->toBeNull();
});

it('update: a restrictive DB row on the mandatory `name` field is ignored (mandatory bypass), write succeeds', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("ea-sectors.{$ability}");
    }

    $role = Role::create(['name' => 'ea-sector-name-locked']);
    $role->givePermissionTo(['ea-sectors.view', 'ea-sectors.update']);
    $role->fieldPermissions()->create([
        'resource' => 'ea-sectors',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = EaSector::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');

    $this->assertDatabaseHas('ea_sectors', ['id' => $target->id, 'name' => 'Changed']);
});
