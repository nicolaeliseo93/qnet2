<?php

declare(strict_types=1);

use App\Models\CustomFieldDefinition;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0021 — T5: CustomFieldAwareAuthorization decorator + MetaController
// enrichment. AC-007 (meta), AC-008 (field-permission matrix on custom.<key>),
// AC-009 (ValidatesFieldPermissionsMatrix).
uses(RefreshDatabase::class);

if (! function_exists('actorWithCompanyAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function actorWithCompanyAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("companies.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("companies.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-007 — GET /meta/companies exposes the enriched custom descriptor,
// native fields stay minimal/unchanged.
// ---------------------------------------------------------------------------

it('AC-007: meta/companies includes the enriched custom field descriptor and permissions.fields entry', function () {
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('enum')->create([
        'key' => 'segment',
        'label' => 'Segment',
        'description' => 'Business segment',
        'help_text' => 'Pick one',
        'group' => 'commercial',
        'tab' => 'details',
        'sort_order' => 3,
        'config' => ['display' => 'select'],
        'validation' => ['required' => true],
    ]);
    $definition->options()->create(['value' => 'retail', 'label' => 'Retail', 'sort_order' => 0]);
    $definition->options()->create(['value' => 'wholesale', 'label' => 'Wholesale', 'sort_order' => 1]);

    $actor = actorWithCompanyAbilities(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/companies')->assertOk();

    $fields = collect($response->json('data.fields'))->keyBy('key');

    expect($fields->has('custom.segment'))->toBeTrue();

    $custom = $fields['custom.segment'];
    expect($custom['source'])->toBe('custom')
        ->and($custom['label'])->toBe('Segment')
        ->and($custom['description'])->toBe('Business segment')
        ->and($custom['help_text'])->toBe('Pick one')
        ->and($custom['group'])->toBe('commercial')
        ->and($custom['tab'])->toBe('details')
        ->and($custom['sort_order'])->toBe(3)
        ->and($custom['mandatory'])->toBeTrue()
        ->and($custom['type'])->toBe('enum')
        ->and($custom['config'])->toBe(['display' => 'select'])
        ->and($custom['options'])->toBe([
            ['value' => 'retail', 'label' => 'Retail', 'color' => null, 'icon' => null],
            ['value' => 'wholesale', 'label' => 'Wholesale', 'color' => null, 'icon' => null],
        ]);

    // Native fields keep the minimal, unchanged descriptor shape.
    $native = $fields['denomination'];
    expect($native)->toHaveKeys(['key', 'type', 'group', 'mandatory'])
        ->and($native)->not->toHaveKey('source');

    $segmentPermission = $response->json('permissions.fields')['custom.segment'];
    expect($segmentPermission['visible'])->toBeTrue()
        ->and($segmentPermission['editable'])->toBeTrue()
        ->and($segmentPermission['required'])->toBeTrue();
});

it('a resource with no active custom fields is unaffected: no custom.* key appears', function () {
    $actor = actorWithCompanyAbilities(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/companies')->assertOk();

    $keys = collect($response->json('data.fields'))->pluck('key');

    expect($keys->contains(fn (string $key): bool => str_starts_with($key, 'custom.')))->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-008 — role_field_permissions on (companies, custom.<key>) is respected;
// super-admin bypasses.
// ---------------------------------------------------------------------------

it('AC-008: visible:false on the matrix hides the custom field in meta', function () {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);

    $role = Role::create(['name' => 'no-notes-role']);
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("companies.{$ability}");
    }
    $role->givePermissionTo(['companies.viewAny', 'companies.create']);
    $role->fieldPermissions()->create(['resource' => 'companies', 'field' => 'custom.notes', 'visible' => false, 'editable' => true, 'required' => false]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $notesPermission = $this->getJson('/api/meta/companies')->assertOk()->json('permissions.fields')['custom.notes'];
    expect($notesPermission['visible'])->toBeFalse()
        ->and($notesPermission['hidden'])->toBeTrue();
});

it('AC-008: editable:false on the matrix makes the custom field readonly', function () {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);

    $role = Role::create(['name' => 'readonly-notes-role']);
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("companies.{$ability}");
    }
    $role->givePermissionTo(['companies.viewAny', 'companies.create']);
    $role->fieldPermissions()->create(['resource' => 'companies', 'field' => 'custom.notes', 'visible' => true, 'editable' => false, 'required' => false]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $notesPermission = $this->getJson('/api/meta/companies')->assertOk()->json('permissions.fields')['custom.notes'];
    expect($notesPermission['editable'])->toBeFalse()
        ->and($notesPermission['readonly'])->toBeTrue();
});

it('AC-008: required:true on the matrix marks the still-editable custom field required', function () {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);

    $role = Role::create(['name' => 'required-notes-role']);
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("companies.{$ability}");
    }
    $role->givePermissionTo(['companies.viewAny', 'companies.create']);
    $role->fieldPermissions()->create(['resource' => 'companies', 'field' => 'custom.notes', 'visible' => true, 'editable' => true, 'required' => true]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $notesPermission = $this->getJson('/api/meta/companies')->assertOk()->json('permissions.fields')['custom.notes'];
    expect($notesPermission['editable'])->toBeTrue()
        ->and($notesPermission['required'])->toBeTrue();
});

it('AC-008: super-admin bypasses any restrictive matrix row on custom.<key>', function () {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);

    $superRole = Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $superRole->fieldPermissions()->create(['resource' => 'companies', 'field' => 'custom.notes', 'visible' => false, 'editable' => false, 'required' => false]);

    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    Sanctum::actingAs($actor);

    $notesPermission = $this->getJson('/api/meta/companies')->assertOk()->json('permissions.fields')['custom.notes'];
    expect($notesPermission['visible'])->toBeTrue()
        ->and($notesPermission['editable'])->toBeTrue()
        ->and($notesPermission['hidden'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-009 — ValidatesFieldPermissionsMatrix: a role can be saved with a matrix
// entry for an existing custom.<key>; an unknown key is rejected.
// ---------------------------------------------------------------------------

it('AC-009: role field_permissions matrix accepts an existing companies custom.<key>', function () {
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);

    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("roles.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.create');
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'accepts-custom-field',
        'field_permissions' => [
            ['resource' => 'companies', 'field' => 'custom.notes', 'visible' => true, 'editable' => false, 'required' => false],
        ],
    ])->assertCreated();

    $role = Role::where('name', 'accepts-custom-field')->firstOrFail();
    $this->assertDatabaseHas('role_field_permissions', [
        'role_id' => $role->id,
        'resource' => 'companies',
        'field' => 'custom.notes',
        'editable' => false,
    ]);
});

it('AC-009: role field_permissions matrix rejects a non-existent custom.<key>', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("roles.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.create');
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'rejects-unknown-custom-field',
        'field_permissions' => [
            ['resource' => 'companies', 'field' => 'custom.ghost', 'visible' => true, 'editable' => true, 'required' => false],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('field_permissions.0.field');

    $this->assertDatabaseMissing('roles', ['name' => 'rejects-unknown-custom-field']);
});
