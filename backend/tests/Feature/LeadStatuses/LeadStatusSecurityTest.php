<?php

use App\Models\LeadStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("lead-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("lead-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// A base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = leadStatusUserWith([]);
    $target = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/lead-statuses/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// DB field-permission matrix (spec 0006/0008): `name` is mandatory, so its
// ceiling can never be narrowed by a DB role_field_permissions row (spec
// 0008) — a restrictive row is ignored and the write still succeeds.
// ---------------------------------------------------------------------------

it('update: a restrictive DB row on the mandatory `name` field is ignored (mandatory bypass), write succeeds', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("lead-statuses.{$ability}");
    }

    $role = Role::create(['name' => 'lead-status-locked']);
    $role->givePermissionTo(['lead-statuses.view', 'lead-statuses.update']);
    $role->fieldPermissions()->create([
        'resource' => 'lead-statuses',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = LeadStatus::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/lead-statuses/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');

    $this->assertDatabaseHas('lead_statuses', ['id' => $target->id, 'name' => 'Changed']);
});

// ---------------------------------------------------------------------------
// AC-010 — a restrictive DB row on the NON-mandatory `color` field IS
// enforced: a role that cannot edit it gets a 422 when it tries to change it.
// ---------------------------------------------------------------------------

it('update: a restrictive DB row on `color` rejects a real change with a 422 (AC-010)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("lead-statuses.{$ability}");
    }

    $role = Role::create(['name' => 'lead-status-color-locked']);
    $role->givePermissionTo(['lead-statuses.view', 'lead-statuses.update']);
    $role->fieldPermissions()->create([
        'resource' => 'lead-statuses',
        'field' => 'color',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = LeadStatus::factory()->create(['name' => 'Original', 'color' => 'slate']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/lead-statuses/{$target->id}", ['color' => 'green'])
        ->assertStatus(422)->assertJsonValidationErrors('color');

    $this->assertDatabaseHas('lead_statuses', ['id' => $target->id, 'color' => 'slate']);
});

// ---------------------------------------------------------------------------
// AC-007 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 lead-statuses.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "lead-statuses.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-017 — navigation node gated by lead-statuses.view
// ---------------------------------------------------------------------------

it('navigation: the lead-statuses node only shows with lead-statuses.view (AC-017)', function () {
    Permission::findOrCreate('lead-statuses.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'configuration'))
        ->not->toContain('lead-statuses');

    $withView = User::factory()->create();
    $withView->givePermissionTo('lead-statuses.view');
    Sanctum::actingAs($withView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'configuration'))
        ->toContain('lead-statuses');
});
