<?php

use App\Models\CustomFieldDefinition;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('operationalSiteUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function operationalSiteUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("operational-sites.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("operational-sites.{$ability}");
        }

        return $user;
    }
}

/**
 * Regression: OperationalSiteService::update() only saved the model when a
 * NATIVE attribute (alias/address) changed, so a custom-fields-only PATCH
 * never fired the model's `saved` event and the HasCustomFields write
 * pipeline (spec 0021) never persisted the value. Mirrors
 * RegistryCustomFieldUpdateTest / RoleCustomFieldUpdateTest for the
 * `operational-sites` domain.
 */
it('update: PATCH with only custom_fields (no alias/address change) persists and reads back the custom values', function () {
    CustomFieldDefinition::factory()->forEntity('operational-sites')->ofType('text')->create(['key' => 'notes']);

    $actor = operationalSiteUserWith(['view', 'update']);
    $site = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/operational-sites/{$site->id}", [
        'custom_fields' => ['notes' => 'written on a fields-only edit'],
    ])->assertOk()
        ->assertJsonPath('data.custom_fields.notes', 'written on a fields-only edit');

    $this->assertDatabaseHas('custom_field_values', [
        'entity_type' => 'operational-sites',
        'entity_id' => $site->id,
    ]);

    expect($site->fresh()->custom_fields)->toBe(['notes' => 'written on a fields-only edit']);

    $this->getJson("/api/operational-sites/{$site->id}")
        ->assertOk()
        ->assertJsonPath('data.custom_fields.notes', 'written on a fields-only edit');
});
