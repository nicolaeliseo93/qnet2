<?php

use App\Authorization\RolesAuthorization;
use App\Authorization\UsersAuthorization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('actorWithRoleAbilities')) {
    /**
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

it('401 without auth', function () {
    $this->getJson('/api/authorization/fields')->assertUnauthorized();
});

it('403 without roles.create or roles.update', function () {
    $actor = actorWithRoleAbilities([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/authorization/fields')->assertForbidden();
});

it('200 with roles.create only', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/authorization/fields')->assertOk();
});

it('200 with roles.update only', function () {
    $actor = actorWithRoleAbilities(['update']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/authorization/fields')->assertOk();
});

it('200 with the catalogue for users and roles, keys matching each resolver\'s fields()', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/authorization/fields')
        ->assertOk()
        ->assertJsonPath('success', true);

    $resources = collect($response->json('data.resources'))->keyBy('resource');

    expect($resources->keys()->all())->toEqualCanonicalizing(['users', 'roles']);

    $userFieldKeys = collect($resources['users']['fields'])->pluck('key')->all();
    $expectedUserKeys = array_map(fn ($field) => $field->key, app(UsersAuthorization::class)->fields());
    expect($userFieldKeys)->toEqualCanonicalizing($expectedUserKeys);

    $roleFieldKeys = collect($resources['roles']['fields'])->pluck('key')->all();
    $expectedRoleKeys = array_map(fn ($field) => $field->key, app(RolesAuthorization::class)->fields());
    expect($roleFieldKeys)->toEqualCanonicalizing($expectedRoleKeys);

    foreach ($resources['users']['fields'] as $field) {
        expect($field)->toHaveKeys(['key', 'type', 'group']);
    }
});
