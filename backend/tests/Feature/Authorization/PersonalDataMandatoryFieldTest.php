<?php

use App\Models\PersonalData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * A role with the given `{resource}.*` abilities and a single restrictive
 * role_field_permissions row on $field, assigned to a fresh actor.
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('actorWithRestrictedField')) {
    function actorWithRestrictedField(string $resource, array $abilities, string $field): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $role = Role::create(['name' => 'restricted-'.str_replace('.', '-', $field).'-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "{$resource}.{$ability}", $abilities));
        $role->fieldPermissions()->create([
            'resource' => $resource,
            'field' => $field,
            'visible' => false,
            'editable' => false,
            'required' => false,
        ]);

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// B — mandatory bypass on read: a restrictive DB row on a mandatory field
// never narrows it — the merge itself skips these keys.
// ---------------------------------------------------------------------------

it('mandatory bypass: a restrictive DB row on users.email cannot hide/lock it (stays visible+editable)', function () {
    $actor = actorWithRestrictedField('users', ['view', 'update'], 'email');
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $field = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields.email');

    expect($field['visible'])->toBeTrue()
        ->and($field['editable'])->toBeTrue()
        ->and($field['hidden'])->toBeFalse();
});

it('mandatory bypass: a restrictive DB row on users.personal_data.first_name cannot hide/lock it (stays visible+editable)', function () {
    $actor = actorWithRestrictedField('users', ['view', 'update'], 'personal_data.first_name');
    $target = User::factory()->create();
    PersonalData::factory()->for($target, 'personable')->create();
    Sanctum::actingAs($actor);

    $field = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields')['personal_data.first_name'];

    expect($field['visible'])->toBeTrue()
        ->and($field['editable'])->toBeTrue();
});

it('mandatory bypass: a restrictive DB row on roles.name cannot hide/lock it (stays visible+editable)', function () {
    $actor = actorWithRestrictedField('roles', ['view', 'update'], 'name');
    $target = Role::create(['name' => 'some-role']);
    Sanctum::actingAs($actor);

    $field = $this->getJson("/api/roles/{$target->id}")->assertOk()->json('permissions.fields.name');

    expect($field['visible'])->toBeTrue()
        ->and($field['editable'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// C — mandatory bypass on write: a mandatory field with a restrictive DB row
// (editable:false) still accepts a CHANGED value — the write-path gate never
// sees it as non-editable, so no 422.
// ---------------------------------------------------------------------------

it('mandatory bypass: PATCH changing users.email passes (200) despite a restrictive DB row', function () {
    $actor = actorWithRestrictedField('users', ['view', 'update'], 'email');
    $target = User::factory()->create(['email' => 'old@example.com']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['email' => 'new@example.com'])->assertOk();

    $this->assertDatabaseHas('users', ['id' => $target->id, 'email' => 'new@example.com']);
});

it('mandatory bypass: PATCH changing users.personal_data.first_name passes (200) despite a restrictive DB row', function () {
    $actor = actorWithRestrictedField('users', ['view', 'update'], 'personal_data.first_name');
    $target = User::factory()->create();
    PersonalData::factory()->individual()->for($target, 'personable')->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => ['type' => 'individual', 'first_name' => 'Grace', 'last_name' => 'Lovelace'],
    ])->assertOk();

    $this->assertDatabaseHas('personal_data', ['personable_id' => $target->id, 'first_name' => 'Grace']);
});

it('mandatory bypass: PATCH renaming roles.name passes (200) despite a restrictive DB row', function () {
    $actor = actorWithRestrictedField('roles', ['view', 'update'], 'name');
    $target = Role::create(['name' => 'before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$target->id}", ['name' => 'after'])->assertOk();

    $this->assertDatabaseHas('roles', ['id' => $target->id, 'name' => 'after']);
});
