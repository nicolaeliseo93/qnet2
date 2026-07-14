<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("lead-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("lead-statuses.{$ability}");
        }

        return $user;
    }
}

it('403 without lead-statuses.viewAny', function () {
    $actor = leadStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/lead-statuses')->assertForbidden();
});

it('200: field catalogue matches the frozen contract, in order (AC-010)', function () {
    $actor = leadStatusUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/lead-statuses')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['name', 'color', 'sort_order']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['name']['mandatory'])->toBeTrue()
        ->and($fields['name']['type'])->toBe('text')
        ->and($fields['color']['mandatory'])->toBeFalse()
        ->and($fields['color']['type'])->toBe('color')
        ->and($fields['sort_order']['type'])->toBe('number');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create (AC-010)', function () {
    $actor = leadStatusUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/lead-statuses')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', true)
        ->assertJsonPath('permissions.fields.name.required', true)
        ->assertJsonPath('permissions.fields.color.editable', true)
        ->assertJsonPath('permissions.fields.sort_order.editable', true);
});

it('permissions.fields are readonly when the actor may not create (AC-010)', function () {
    $actor = leadStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/lead-statuses')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', false)
        ->assertJsonPath('permissions.fields.name.readonly', true);
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = leadStatusUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/lead-statuses')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});
