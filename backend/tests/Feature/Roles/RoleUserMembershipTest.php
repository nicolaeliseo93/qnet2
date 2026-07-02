<?php

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('actorWithRoleAbilities')) {
    /**
     * A non super-admin actor granted exactly the given `roles.*` abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function actorWithRoleAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("roles.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("roles.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('superAdminActor')) {
    function superAdminActor(): User
    {
        Role::findOrCreate(RoleAssignmentGuard::PRIVILEGED_ROLE);
        $actor = User::factory()->create();
        $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// Happy path — create / update sync membership
// ---------------------------------------------------------------------------

it('create: syncs members from the users list', function () {
    $actor = actorWithRoleAbilities(['create']);
    $a = User::factory()->create();
    $b = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'editors',
        'users' => [$a->id, $b->id],
    ])->assertCreated();

    $role = Role::where('name', 'editors')->first();
    expect($role->users->pluck('id')->all())->toEqualCanonicalizing([$a->id, $b->id]);
});

it('update: syncs members from the users list', function () {
    $actor = actorWithRoleAbilities(['update']);
    $role = Role::create(['name' => 'team']);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $role->users()->sync([$a->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$role->id}", ['users' => [$b->id]])->assertOk();

    expect($role->fresh()->users->pluck('id')->all())->toEqualCanonicalizing([$b->id]);
});

it('update: users:[] removes all members', function () {
    $actor = actorWithRoleAbilities(['update']);
    $role = Role::create(['name' => 'team']);
    $role->users()->sync([User::factory()->create()->id, User::factory()->create()->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$role->id}", ['users' => []])->assertOk();

    expect($role->fresh()->users)->toBeEmpty();
});

it('update: omitting users leaves membership untouched', function () {
    $actor = actorWithRoleAbilities(['update']);
    $role = Role::create(['name' => 'team']);
    $member = User::factory()->create();
    $role->users()->sync([$member->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$role->id}", ['name' => 'renamed'])->assertOk();

    expect($role->fresh()->users->pluck('id')->all())->toEqual([$member->id]);
});

// ---------------------------------------------------------------------------
// Resource projection — RoleResource exposes member ids
// ---------------------------------------------------------------------------

it('view: role detail includes the assigned member ids', function () {
    $actor = actorWithRoleAbilities(['view']);
    $role = Role::create(['name' => 'team']);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $role->users()->sync([$a->id, $b->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/roles/{$role->id}")->assertOk();

    expect($response->json('data.users'))->toEqualCanonicalizing([$a->id, $b->id]);
});

it('view: role with no members returns an empty users array', function () {
    $actor = actorWithRoleAbilities(['view']);
    $role = Role::create(['name' => 'team']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/roles/{$role->id}")
        ->assertOk()
        ->assertJsonPath('data.users', []);
});

it('create: response echoes the assigned member ids', function () {
    $actor = actorWithRoleAbilities(['create']);
    $a = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/roles', [
        'name' => 'editors',
        'users' => [$a->id],
    ])->assertCreated();

    expect($response->json('data.users'))->toEqual([$a->id]);
});

it('update: response reflects the synced member ids', function () {
    $actor = actorWithRoleAbilities(['update']);
    $role = Role::create(['name' => 'team']);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $role->users()->sync([$a->id]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/roles/{$role->id}", ['users' => [$b->id]])->assertOk();

    expect($response->json('data.users'))->toEqual([$b->id]);
});

it('view: member ids reflect the privilege guard, not the requested escalation', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = actorWithRoleAbilities(['update', 'view']);
    $victim = User::factory()->create();
    $superRole = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    // A non-super-admin's super-admin membership change is filtered out, so the
    // returned projection must show the unchanged (empty) membership — no leak,
    // no escalation surfaced through the resource.
    $response = $this->patchJson("/api/roles/{$superRole->id}", ['users' => [$victim->id]])->assertOk();

    expect($response->json('data.users'))->toBe([]);
});

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

it('update: 403 without roles.update', function () {
    $actor = actorWithRoleAbilities([]);
    $role = Role::create(['name' => 'team']);
    $member = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$role->id}", ['users' => [$member->id]])->assertForbidden();
});

it('create: 422 when a user id does not exist', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', ['name' => 'ghosts', 'users' => [999999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('users.0');
});

// ---------------------------------------------------------------------------
// Privilege escalation — super-admin membership
// ---------------------------------------------------------------------------

it('non-super-admin actor CANNOT add a super-admin member via role membership', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = actorWithRoleAbilities(['update']);
    $victim = User::factory()->create();
    $superRole = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    // roles.update lets them hit the endpoint, but the guard rejects the
    // super-admin membership change (membership left unchanged, no escalation).
    $this->patchJson("/api/roles/{$superRole->id}", ['users' => [$victim->id]])->assertOk();

    expect($victim->fresh()->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE))->toBeFalse();
});

it('super-admin actor CAN add a super-admin member via role membership', function () {
    $actor = superAdminActor(); // Gate::before grants roles.update
    $newAdmin = User::factory()->create();
    $superRole = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    // Keep the existing super-admin (actor) and add the new one.
    $this->patchJson("/api/roles/{$superRole->id}", ['users' => [$actor->id, $newAdmin->id]])
        ->assertOk();

    expect($newAdmin->fresh()->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE))->toBeTrue()
        ->and($actor->fresh()->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE))->toBeTrue();
});

it('blocks shrinking super-admin membership below the last super-admin (422)', function () {
    $actor = superAdminActor(); // the only super-admin
    $superRole = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    // Try to remove every member (the last super-admin) via membership sync.
    $this->patchJson("/api/roles/{$superRole->id}", ['users' => []])
        ->assertStatus(422);

    expect($actor->fresh()->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE))->toBeTrue();
});

it('super-admin can remove a member while another super-admin remains', function () {
    $actor = superAdminActor();
    $second = User::factory()->create();
    $superRole = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    $superRole->users()->sync([$actor->id, $second->id]);
    Sanctum::actingAs($actor);

    // Remove the second admin; the actor remains → allowed.
    $this->patchJson("/api/roles/{$superRole->id}", ['users' => [$actor->id]])
        ->assertOk();

    expect($second->fresh()->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE))->toBeFalse()
        ->and($actor->fresh()->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE))->toBeTrue();
});

it('super-admin role name/permission mutation is still blocked (422), membership aside', function () {
    $actor = superAdminActor();
    $superRole = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$superRole->id}", ['name' => 'hacked'])
        ->assertStatus(422);

    $this->assertDatabaseHas('roles', ['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
});
