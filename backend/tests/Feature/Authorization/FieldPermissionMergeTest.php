<?php

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * A role with the given `users.*` abilities and an optional single
 * role_field_permissions row, assigned to a fresh actor.
 *
 * @param  array<int, string>  $abilities
 * @param  array<string, mixed>|null  $matrixRow
 */
if (! function_exists('actorWithFieldPermissionRole')) {
    function actorWithFieldPermissionRole(array $abilities, ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $role = Role::create(['name' => 'field-perm-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "users.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC 5 — merge: restriction works.
// ---------------------------------------------------------------------------

it('a role config restricting users.email visible:false hides the field for a member', function () {
    $actor = actorWithFieldPermissionRole(
        ['view', 'update'],
        ['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false],
    );
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.email.visible', false)
        ->assertJsonPath('permissions.fields.email.hidden', true);
});

// ---------------------------------------------------------------------------
// AC 6 — merge: no escalation (the ceiling always wins).
// ---------------------------------------------------------------------------

it('DB editable:true on users.roles cannot override the super-admin-target ceiling lock', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = actorWithFieldPermissionRole(
        ['view', 'update'],
        ['resource' => 'users', 'field' => 'roles', 'visible' => true, 'editable' => true, 'required' => false],
    );
    $target = User::factory()->create();
    $target->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.roles.editable', false)
        ->assertJsonPath('permissions.fields.roles.readonly', true);
});

it('DB editable:true has no effect when the actor lacks the base users.update ability', function () {
    $actor = actorWithFieldPermissionRole(
        ['view'], // no users.update
        ['resource' => 'users', 'field' => 'locale', 'visible' => true, 'editable' => true, 'required' => false],
    );
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.locale.editable', false)
        ->assertJsonPath('permissions.fields.locale.readonly', true);
});

// ---------------------------------------------------------------------------
// AC 7 — union across roles.
// ---------------------------------------------------------------------------

it('union across roles: role A hides email, role B shows it — actor with both sees it visible', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("users.{$ability}");
    }

    $roleA = Role::create(['name' => 'hides-email']);
    $roleA->givePermissionTo(['users.view', 'users.update']);
    $roleA->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false]);

    $roleB = Role::create(['name' => 'shows-email']);
    $roleB->givePermissionTo(['users.view', 'users.update']);
    $roleB->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => true, 'editable' => true, 'required' => false]);

    $actor = User::factory()->create();
    $actor->assignRole([$roleA->name, $roleB->name]);

    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.email.visible', true);
});

// ---------------------------------------------------------------------------
// AC 8 — privileged bypass.
// ---------------------------------------------------------------------------

it('a super-admin actor is unaffected by any restrictive DB config on their own role', function () {
    $superRole = Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $superRole->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => false, 'required' => false]);

    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.email.visible', true)
        ->assertJsonPath('permissions.fields.email.editable', true)
        ->assertJsonPath('permissions.fields.email.hidden', false);
});
