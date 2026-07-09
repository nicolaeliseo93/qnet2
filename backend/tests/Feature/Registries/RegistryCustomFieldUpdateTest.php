<?php

use App\Models\CustomFieldDefinition;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('registryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function registryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("registries.{$ability}");
        }

        return $user;
    }
}

/**
 * Regression (spec 0021 write pipeline): a PATCH carrying ONLY custom_fields
 * — no native registry attribute — must still persist the custom values. The
 * write hooks the model's `saved` event, so a service that skips the model
 * save when no native attribute changed would silently drop the values.
 */
it('update: PATCH with only custom_fields (no native attribute) persists the custom values', function () {
    CustomFieldDefinition::factory()->forEntity('registries')->ofType('text')->create(['key' => 'notes']);

    $actor = registryUserWith(['update']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$registry->id}", [
        'custom_fields' => ['notes' => 'written on a fields-only edit'],
    ])->assertOk();

    $this->assertDatabaseHas('custom_field_values', [
        'entity_type' => 'registries',
        'entity_id' => $registry->id,
    ]);

    expect($registry->fresh()->custom_fields)->toBe(['notes' => 'written on a fields-only edit']);
});
