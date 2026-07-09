<?php

use App\Models\CustomFieldDefinition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('roleUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function roleUserWith(array $abilities): User
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

/**
 * Regression: Role was missing App\Models\Concerns\HasCustomFields (spec
 * 0021), so a PATCH carrying only custom_fields never persisted them (the
 * trait's `saving`/`saved` observers never ran) and GET /roles/{id} never
 * surfaced them (BaseApiController::withCustomFields skips models without
 * the trait). Mirrors RegistryCustomFieldUpdateTest for the `registries`
 * domain.
 */
it('update: PATCH with only custom_fields (no native attribute) persists and reads back the custom values', function () {
    CustomFieldDefinition::factory()->forEntity('roles')->ofType('text')->create(['key' => 'notes']);

    $actor = roleUserWith(['view', 'update']);
    $role = Role::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$role->id}", [
        'custom_fields' => ['notes' => 'written on a fields-only edit'],
    ])->assertOk()
        ->assertJsonPath('data.custom_fields.notes', 'written on a fields-only edit');

    $this->assertDatabaseHas('custom_field_values', [
        'entity_type' => 'roles',
        'entity_id' => $role->id,
    ]);

    expect($role->fresh()->custom_fields)->toBe(['notes' => 'written on a fields-only edit']);

    $this->getJson("/api/roles/{$role->id}")
        ->assertOk()
        ->assertJsonPath('data.custom_fields.notes', 'written on a fields-only edit');
});
