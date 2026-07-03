<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithUserAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithUserAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC 1 — 401 / 404 / 403
// ---------------------------------------------------------------------------

it('401 without auth', function () {
    $this->getJson('/api/meta/users')->assertUnauthorized();
});

it('404 for an unknown resource', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/does-not-exist')->assertNotFound();
});

it('403 without {resource}.viewAny', function () {
    $actor = userWithUserAbilities([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/users')->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC 2 — 200 with field catalogue + full permissions block
// ---------------------------------------------------------------------------

it('200: returns the field catalogue and the full permissions block (create-context)', function () {
    $actor = userWithUserAbilities(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/users')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'OK')
        ->assertJsonPath('permissions.resource.create', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toEqualCanonicalizing(['email', 'locale', 'roles', 'password']);

    foreach ($response->json('data.fields') as $field) {
        expect($field)->toHaveKeys(['key', 'type', 'group']);
    }

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create', function () {
    $actor = userWithUserAbilities(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/users')
        ->assertOk()
        ->assertJsonPath('permissions.fields.email.editable', true)
        ->assertJsonPath('permissions.fields.email.required', true)
        // password is only required in create-context, which this is.
        ->assertJsonPath('permissions.fields.password.required', true);
});

// ---------------------------------------------------------------------------
// AC 3 — permissions.resource reflects the actor, incl. export/import
// ---------------------------------------------------------------------------

it('permissions.resource reflects the actor abilities including export/import', function () {
    $actor = userWithUserAbilities(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/users')
        ->assertOk()
        ->assertJsonPath('permissions.resource.view', false)
        ->assertJsonPath('permissions.resource.create', false)
        ->assertJsonPath('permissions.resource.update', false)
        ->assertJsonPath('permissions.resource.delete', false)
        ->assertJsonPath('permissions.resource.export', true)
        ->assertJsonPath('permissions.resource.import', false);
});

it('works for the roles resource too (registry-driven, not users-specific)', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("roles.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.viewAny');
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/roles')->assertOk();

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toEqualCanonicalizing(['name', 'permissions', 'users']);
});
