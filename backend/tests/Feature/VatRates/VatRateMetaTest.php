<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('vatRateUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function vatRateUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("vat-rates.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("vat-rates.{$ability}");
        }

        return $user;
    }
}

it('403 without vat-rates.viewAny', function () {
    $actor = vatRateUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/vat-rates')->assertForbidden();
});

it('200: field catalogue matches the frozen contract, in order', function () {
    $actor = vatRateUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/vat-rates')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['name', 'rate']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['name']['mandatory'])->toBeTrue()
        ->and($fields['name']['type'])->toBe('text')
        ->and($fields['rate']['mandatory'])->toBeTrue()
        ->and($fields['rate']['type'])->toBe('number');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create', function () {
    $actor = vatRateUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/vat-rates')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', true)
        ->assertJsonPath('permissions.fields.rate.editable', true);
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = vatRateUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/vat-rates')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', false)
        ->assertJsonPath('permissions.fields.name.readonly', true);
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = vatRateUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/vat-rates')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});
