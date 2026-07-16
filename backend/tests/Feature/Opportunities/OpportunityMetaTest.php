<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityMetaUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityMetaUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

const OPPORTUNITY_FIELD_KEYS = [
    'name', 'registry_id', 'company_id', 'company_site_id', 'operational_site_id',
    'business_function_id', 'referent_id', 'commercial_id', 'reporter_id', 'supervisor_id',
    'source_id', 'product_category_id', 'manager_slots', 'start_date', 'estimated_value',
    'expected_close_date', 'success_probability',
];

// ---------------------------------------------------------------------------
// AC-031 — GET /api/meta/opportunities: the 17 fields, lead_id absent
// ---------------------------------------------------------------------------

it('403 without opportunities.viewAny', function () {
    $actor = opportunityMetaUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')->assertForbidden();
});

it('200: field catalogue has the 17 contract fields, in order, lead_id absent (AC-031)', function () {
    $actor = opportunityMetaUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(OPPORTUNITY_FIELD_KEYS);
    expect($keys)->not->toContain('lead_id');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create, the 5 mandatory fields required (AC-031/AC-083)', function () {
    $actor = opportunityMetaUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', true)
        ->assertJsonPath('permissions.fields.name.required', true)
        ->assertJsonPath('permissions.fields.registry_id.required', true)
        ->assertJsonPath('permissions.fields.company_id.required', true)
        ->assertJsonPath('permissions.fields.company_site_id.required', true)
        ->assertJsonPath('permissions.fields.operational_site_id.required', true)
        ->assertJsonPath('permissions.fields.business_function_id.required', false)
        ->assertJsonPath('permissions.fields.estimated_value.required', false);
});

it('200: the mandatory fields are not restrictable by field-permissions (AC-083)', function () {
    $role = Role::create(['name' => 'opportunity-company-locked']);
    foreach (['viewAny', 'create'] as $ability) {
        Permission::findOrCreate("opportunities.{$ability}");
    }
    $role->givePermissionTo(['opportunities.viewAny', 'opportunities.create']);
    $role->fieldPermissions()->create([
        'resource' => 'opportunities',
        'field' => 'company_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    // A DB row attempting to narrow a mandatory field is ignored (spec 0008
    // ceiling bypass for mandatory fields) — company_id stays editable+required.
    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.company_id.editable', true)
        ->assertJsonPath('permissions.fields.company_id.required', true);
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = opportunityMetaUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', false)
        ->assertJsonPath('permissions.fields.name.readonly', true);
});

// ---------------------------------------------------------------------------
// AC-033 — the resource surfaces in the Role matrix's field catalogue too
// ---------------------------------------------------------------------------

it('GET /api/authorization/fields includes opportunities with its 17 fields (AC-033)', function () {
    foreach (['viewAny', 'create'] as $ability) {
        Permission::findOrCreate("roles.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.create');
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/authorization/fields')->assertOk();

    $resources = collect($response->json('data.resources'))->pluck('resource');
    expect($resources)->toContain('opportunities');

    $entry = collect($response->json('data.resources'))->firstWhere('resource', 'opportunities');
    $keys = collect($entry['fields'])->pluck('key')->all();
    expect($keys)->toBe(OPPORTUNITY_FIELD_KEYS);
});
