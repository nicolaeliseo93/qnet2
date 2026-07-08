<?php

use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * A role with the given `registries.*` abilities and an optional single
 * role_field_permissions row, assigned to a fresh actor. Mirrors
 * PersonalDataFieldPermissionsTest's actorWithFieldPermissionRole.
 *
 * @param  array<int, string>  $abilities
 * @param  array<string, mixed>|null  $matrixRow
 */
if (! function_exists('registryActorWithFieldPermissionRole')) {
    function registryActorWithFieldPermissionRole(array $abilities, ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }

        $role = Role::create(['name' => 'registry-field-perm-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "registries.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-013 — EnforcesFieldPermissions: a changed value on a non-editable field
// is rejected (422); an unchanged/absent value is a no-op.
// ---------------------------------------------------------------------------

it('AC-013: PATCH with a CHANGED value on a non-editable field is rejected (422)', function () {
    $actor = registryActorWithFieldPermissionRole(
        ['view', 'update'],
        ['resource' => 'registries', 'field' => 'vat_group', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $registry = Registry::factory()->create(['vat_group' => 'VG-OLD']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['vat_group' => 'VG-NEW'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('vat_group');

    expect($registry->fresh()->vat_group)->toBe('VG-OLD');
});

it('AC-013: PATCH re-submitting the SAME value on a non-editable field is a no-op (200)', function () {
    $actor = registryActorWithFieldPermissionRole(
        ['view', 'update'],
        ['resource' => 'registries', 'field' => 'vat_group', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $registry = Registry::factory()->create(['vat_group' => 'VG-SAME']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['vat_group' => 'VG-SAME', 'employee_count' => 42])
        ->assertOk()
        ->assertJsonPath('data.vat_group', 'VG-SAME')
        ->assertJsonPath('data.employee_count', 42);
});

it('AC-013: base authz (403) is checked BEFORE any field-level 422', function () {
    $actor = registryActorWithFieldPermissionRole(
        [], // no registries.update at all
        ['resource' => 'registries', 'field' => 'vat_group', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $registry = Registry::factory()->create(['vat_group' => 'VG-OLD']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", ['vat_group' => 'VG-NEW'])->assertForbidden();
});

it('AC-013: personal_data.* fields are also enforced by the field-permission gate', function () {
    $actor = registryActorWithFieldPermissionRole(
        ['view', 'update'],
        ['resource' => 'registries', 'field' => 'personal_data.tax_code', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $registry = Registry::factory()->withPersonalData(fn ($card) => $card->individual()->state(['tax_code' => 'OLDTAX']))->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", [
        'personal_data' => minimalRegistryProfilePayload(['tax_code' => 'NEWTAX']),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.tax_code');
});
