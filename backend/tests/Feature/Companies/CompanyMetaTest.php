<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanyAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanyAbilities(array $abilities): User
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

it('403 without companies.viewAny', function () {
    $actor = userWithCompanyAbilities([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/companies')->assertForbidden();
});

it('200: field catalogue matches the frozen contract, in order', function () {
    $actor = userWithCompanyAbilities(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/companies')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['denomination', 'vat_number', 'address']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['denomination']['mandatory'])->toBeTrue()
        ->and($fields['vat_number']['mandatory'])->toBeFalse()
        ->and($fields['address']['mandatory'])->toBeFalse()
        ->and($fields['denomination']['type'])->toBe('text')
        ->and($fields['vat_number']['type'])->toBe('text')
        ->and($fields['address']['type'])->toBe('collection');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create', function () {
    $actor = userWithCompanyAbilities(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/companies')
        ->assertOk()
        ->assertJsonPath('permissions.fields.denomination.editable', true)
        ->assertJsonPath('permissions.fields.denomination.required', true)
        ->assertJsonPath('permissions.fields.address.editable', true);
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/companies')
        ->assertOk()
        ->assertJsonPath('permissions.fields.denomination.editable', false)
        ->assertJsonPath('permissions.fields.denomination.readonly', true);
});

it('permissions.resource reflects the actor abilities including export/import', function () {
    $actor = userWithCompanyAbilities(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/companies')
        ->assertOk()
        ->assertJsonPath('permissions.resource.view', false)
        ->assertJsonPath('permissions.resource.create', false)
        ->assertJsonPath('permissions.resource.export', true)
        ->assertJsonPath('permissions.resource.import', false);
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = userWithCompanyAbilities(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/companies')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});
