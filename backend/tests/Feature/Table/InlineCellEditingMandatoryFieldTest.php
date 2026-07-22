<?php

use App\Models\Opportunity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// PATCH /api/tables/{domain}/rows/{row} — mandatory-field enforcement (user
// directive): a field resolved as `required` (App\Authorization\FieldPermission)
// rejects null/blank on inline edit even though it stays editable — derived
// from the SAME field-permission metadata both channels already share, not a
// per-column declaration, so it applies to every mandatory field, present or
// future.

uses(RefreshDatabase::class);

if (! function_exists('mandatoryFieldActor')) {
    function mandatoryFieldActor(): User
    {
        foreach (['viewAny', 'update'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['opportunities.viewAny', 'opportunities.update']);

        return $user;
    }
}

// ---------------------------------------------------------------------------
// `name` — mandatory in the CEILING itself (OpportunitiesAuthorization),
// already declared non-nullable in the catalog: both agree.
// ---------------------------------------------------------------------------

it('a mandatory field rejects an empty string -> 422, no write', function () {
    $actor = mandatoryFieldActor();
    $opportunity = Opportunity::factory()->create(['name' => 'Kept name']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'name',
        'value' => '',
    ])->assertStatus(422);

    expect($opportunity->fresh()->name)->toBe('Kept name');
});

it('a mandatory field rejects a whitespace-only string -> 422, no write', function () {
    $actor = mandatoryFieldActor();
    $opportunity = Opportunity::factory()->create(['name' => 'Kept name']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'name',
        'value' => '   ',
    ])->assertStatus(422);

    expect($opportunity->fresh()->name)->toBe('Kept name');
});

it('a mandatory field accepts a genuine value -> 200, persisted', function () {
    $actor = mandatoryFieldActor();
    $opportunity = Opportunity::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'name',
        'value' => 'After',
    ])->assertOk();

    expect($opportunity->fresh()->name)->toBe('After');
});

// ---------------------------------------------------------------------------
// Conflict: the CATALOG declares `estimated_value` nullable, but the actor's
// DB field-permission matrix marks it `required` — the more restrictive of
// the two must win (`required` overrides the column's own `nullable`).
// ---------------------------------------------------------------------------

it('a DB-matrix `required:true` on an otherwise-nullable column rejects null (most restrictive wins)', function () {
    Permission::findOrCreate('opportunities.viewAny');
    Permission::findOrCreate('opportunities.update');

    $role = Role::create(['name' => 'estimated-value-required-'.uniqid()]);
    $role->givePermissionTo(['opportunities.viewAny', 'opportunities.update']);
    $role->fieldPermissions()->create([
        'resource' => 'opportunities', 'field' => 'estimated_value', 'visible' => true, 'editable' => true, 'required' => true,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $opportunity = Opportunity::factory()->create(['estimated_value' => 500]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'estimated_value',
        'value' => null,
    ])->assertStatus(422);

    expect((float) $opportunity->fresh()->estimated_value)->toBe(500.0);
});

it('without the DB-matrix override, the same nullable column still accepts null (regression)', function () {
    $actor = mandatoryFieldActor();
    $opportunity = Opportunity::factory()->create(['estimated_value' => 500]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", [
        'column' => 'estimated_value',
        'value' => null,
    ])->assertOk();

    expect($opportunity->fresh()->estimated_value)->toBeNull();
});
