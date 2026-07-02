<?php

use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithRoleAbilities')) {
    /**
     * A non super-admin actor granted exactly the given `roles.*` abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function userWithRoleAbilities(array $abilities): User
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

// ---------------------------------------------------------------------------
// view — GET /api/roles/{role}
// ---------------------------------------------------------------------------

it('view: 200 with roles.view', function () {
    $actor = userWithRoleAbilities(['view']);
    $target = Role::create(['name' => 'editor']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/roles/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'editor');
});

it('view: data.users contains the assigned member ids', function () {
    $actor = userWithRoleAbilities(['view']);
    $target = Role::create(['name' => 'editor']);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $target->users()->sync([$a->id, $b->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/roles/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id);

    expect($response->json('data.users'))->toEqualCanonicalizing([$a->id, $b->id]);
});

it('view: data.users is [] for a role with no members', function () {
    $actor = userWithRoleAbilities(['view']);
    $target = Role::create(['name' => 'lonely']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/roles/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.users', []);
});

it('view: resolves data.users with a single bounded member query (no N+1)', function () {
    $actor = userWithRoleAbilities(['view']);
    $target = Role::create(['name' => 'team']);
    $memberIds = [];
    for ($i = 0; $i < 5; $i++) {
        $memberIds[] = User::factory()->create()->id;
    }
    $target->users()->sync($memberIds);
    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->getJson("/api/roles/{$target->id}")
        ->assertOk()
        ->assertJsonCount(5, 'data.users');
    // Member ids are projected by joining users onto the role pivot, so the
    // member query both reads from `users` and joins `model_has_roles`. The
    // actor's own authorization also touches the pivot, so we isolate the
    // member-projection query by its join to the `users` table.
    $memberQueries = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains($q['query'], 'model_has_roles')
            && str_contains($q['query'], 'users'))
        ->count();
    DB::disableQueryLog();

    // All five member ids are read with one bounded pivot query — the member
    // projection does not issue a query per member (no N+1).
    expect($memberQueries)->toBe(1);
});

it('view: 403 without roles.view', function () {
    $actor = userWithRoleAbilities([]);
    $target = Role::create(['name' => 'editor']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/roles/{$target->id}")->assertForbidden();
});

it('view: 404 for a non-existent role', function () {
    $actor = userWithRoleAbilities(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/roles/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/roles
// ---------------------------------------------------------------------------

it('create: 201 + persistence', function () {
    $actor = userWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', ['name' => 'manager'])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'manager');

    $this->assertDatabaseHas('roles', ['name' => 'manager']);
});

it('create: attaches existing permissions', function () {
    Permission::findOrCreate('users.view');
    Permission::findOrCreate('users.create');
    $actor = userWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'support',
        'permissions' => ['users.view', 'users.create'],
    ])->assertCreated();

    $role = Role::where('name', 'support')->first();
    expect($role->hasPermissionTo('users.view'))->toBeTrue()
        ->and($role->hasPermissionTo('users.create'))->toBeTrue();
});

it('create: 403 without roles.create', function () {
    $actor = userWithRoleAbilities([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', ['name' => 'nope'])->assertForbidden();
});

it('create: 422 on duplicate name', function () {
    Role::create(['name' => 'dup']);
    $actor = userWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', ['name' => 'dup'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 on a permission that does not exist', function () {
    $actor = userWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'ghost',
        'permissions' => ['does.not.exist'],
    ])->assertStatus(422)->assertJsonValidationErrors('permissions.0');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/roles/{role}
// ---------------------------------------------------------------------------

it('update: 200 renames and re-syncs permissions', function () {
    Permission::findOrCreate('users.view');
    $actor = userWithRoleAbilities(['update']);
    $target = Role::create(['name' => 'before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$target->id}", [
        'name' => 'after',
        'permissions' => ['users.view'],
    ])->assertOk()->assertJsonPath('data.name', 'after');

    $this->assertDatabaseHas('roles', ['id' => $target->id, 'name' => 'after']);
    expect($target->fresh()->hasPermissionTo('users.view'))->toBeTrue();
});

it('update: PATCH partial leaves permissions untouched', function () {
    Permission::findOrCreate('users.view');
    $actor = userWithRoleAbilities(['update']);
    $target = Role::create(['name' => 'keep']);
    $target->givePermissionTo('users.view');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$target->id}", ['name' => 'renamed'])->assertOk();

    expect($target->fresh()->hasPermissionTo('users.view'))->toBeTrue();
});

it('update: name unique ignores the role being edited', function () {
    $actor = userWithRoleAbilities(['update']);
    $target = Role::create(['name' => 'self']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$target->id}", ['name' => 'self'])->assertOk();
});

it('update: 422 when name collides with another role', function () {
    $actor = userWithRoleAbilities(['update']);
    Role::create(['name' => 'taken']);
    $target = Role::create(['name' => 'mine']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$target->id}", ['name' => 'taken'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('update: 403 without roles.update', function () {
    $actor = userWithRoleAbilities([]);
    $target = Role::create(['name' => 'editor']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$target->id}", ['name' => 'nope'])->assertForbidden();
});

it('update: 422 cannot modify the protected super-admin role', function () {
    Role::create(['name' => UserService::PRIVILEGED_ROLE]);
    $actor = userWithRoleAbilities(['update']);
    $target = Role::where('name', UserService::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$target->id}", ['name' => 'hacked'])
        ->assertStatus(422);

    $this->assertDatabaseHas('roles', ['name' => UserService::PRIVILEGED_ROLE]);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/roles/{role}
// ---------------------------------------------------------------------------

it('delete: 204 and removes the role', function () {
    $actor = userWithRoleAbilities(['delete']);
    $target = Role::create(['name' => 'temp']);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/roles/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('roles', ['id' => $target->id]);
});

it('delete: 403 without roles.delete', function () {
    $actor = userWithRoleAbilities([]);
    $target = Role::create(['name' => 'editor']);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/roles/{$target->id}")->assertForbidden();
});

it('delete: 422 cannot delete the protected super-admin role', function () {
    Role::create(['name' => UserService::PRIVILEGED_ROLE]);
    $actor = userWithRoleAbilities(['delete']);
    $target = Role::where('name', UserService::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/roles/{$target->id}")->assertStatus(422);

    $this->assertDatabaseHas('roles', ['name' => UserService::PRIVILEGED_ROLE]);
});
