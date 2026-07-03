<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('businessFunctionUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function businessFunctionUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/business-functions/{businessFunction} (AC-009)
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = businessFunctionUserWith(['view']);
    $manager = User::factory()->create(['name' => 'Ada Lovelace']);
    $member = User::factory()->create(['name' => 'Grace Hopper']);
    $target = BusinessFunction::factory()->create([
        'name' => 'Engineering',
        'is_business_unit' => true,
        'is_business_service' => false,
        'manager_id' => $manager->id,
    ]);
    $target->users()->sync([$member->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/business-functions/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Engineering')
        ->assertJsonPath('data.is_business_unit', true)
        ->assertJsonPath('data.is_business_service', false)
        ->assertJsonPath('data.type', 'business_unit')
        ->assertJsonPath('data.manager_id', $manager->id)
        ->assertJsonPath('data.manager.id', $manager->id)
        ->assertJsonPath('data.manager.name', 'Ada Lovelace')
        ->assertJsonPath('data.user_ids', [$member->id])
        ->assertJsonPath('data.users.0.name', 'Grace Hopper');

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: type is null when neither boolean is set', function () {
    $actor = businessFunctionUserWith(['view']);
    $target = BusinessFunction::factory()->create([
        'is_business_unit' => false,
        'is_business_service' => false,
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/business-functions/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.type', null)
        ->assertJsonPath('data.manager', null)
        ->assertJsonPath('data.user_ids', []);
});

it('show: 403 without business-functions.view', function () {
    $actor = businessFunctionUserWith([]);
    $target = BusinessFunction::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/business-functions/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent function', function () {
    $actor = businessFunctionUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/business-functions/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/business-functions (AC-007/008)
// ---------------------------------------------------------------------------

it('create: 201 + persists with type business_unit mapped to booleans', function () {
    $actor = businessFunctionUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/business-functions', [
        'name' => 'Sales',
        'type' => 'business_unit',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Sales')
        ->assertJsonPath('data.is_business_unit', true)
        ->assertJsonPath('data.is_business_service', false)
        ->assertJsonPath('data.type', 'business_unit');

    $this->assertDatabaseHas('business_functions', [
        'name' => 'Sales',
        'is_business_unit' => true,
        'is_business_service' => false,
    ]);
});

it('create: type business_service mapped to booleans (inverse)', function () {
    $actor = businessFunctionUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/business-functions', [
        'name' => 'Support',
        'type' => 'business_service',
    ])->assertCreated()
        ->assertJsonPath('data.is_business_unit', false)
        ->assertJsonPath('data.is_business_service', true)
        ->assertJsonPath('data.type', 'business_service');
});

it('create: type absent maps both booleans to false', function () {
    $actor = businessFunctionUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/business-functions', ['name' => 'Neutral'])
        ->assertCreated()
        ->assertJsonPath('data.is_business_unit', false)
        ->assertJsonPath('data.is_business_service', false)
        ->assertJsonPath('data.type', null);
});

it('create: 422 on an invalid type', function () {
    $actor = businessFunctionUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/business-functions', ['name' => 'Bad', 'type' => 'not-a-type'])
        ->assertStatus(422)->assertJsonValidationErrors('type');
});

it('create: hydrates manager and users', function () {
    $actor = businessFunctionUserWith(['create']);
    $manager = User::factory()->create();
    $member = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/business-functions', [
        'name' => 'Ops',
        'manager_id' => $manager->id,
        'users' => [$member->id],
    ])->assertCreated()
        ->assertJsonPath('data.manager.id', $manager->id)
        ->assertJsonPath('data.user_ids', [$member->id]);
});

it('create: 422 when name is missing', function () {
    $actor = businessFunctionUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/business-functions', [])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when manager_id does not exist', function () {
    $actor = businessFunctionUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/business-functions', ['name' => 'X', 'manager_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('manager_id');
});

it('create: 422 when users contains a non-existent id', function () {
    $actor = businessFunctionUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/business-functions', ['name' => 'X', 'users' => [999999]])
        ->assertStatus(422)->assertJsonValidationErrors('users.0');
});

it('create: 403 without business-functions.create', function () {
    $actor = businessFunctionUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/business-functions', ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/business-functions/{businessFunction} (AC-008/010)
// ---------------------------------------------------------------------------

it('update: PATCH partial (only users) full-replaces the relation, other fields untouched', function () {
    $actor = businessFunctionUserWith(['update']);
    $target = BusinessFunction::factory()->create(['name' => 'Keep Me']);
    $old = User::factory()->create();
    $new = User::factory()->create();
    $target->users()->sync([$old->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/business-functions/{$target->id}", ['users' => [$new->id]])
        ->assertOk()
        ->assertJsonPath('data.name', 'Keep Me')
        ->assertJsonPath('data.user_ids', [$new->id]);

    expect($target->fresh()->users->pluck('id')->all())->toBe([$new->id]);
});

it('update: PATCH {manager_id: null} removes the manager', function () {
    $actor = businessFunctionUserWith(['update']);
    $manager = User::factory()->create();
    $target = BusinessFunction::factory()->create(['manager_id' => $manager->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/business-functions/{$target->id}", ['manager_id' => null])
        ->assertOk()
        ->assertJsonPath('data.manager_id', null)
        ->assertJsonPath('data.manager', null);

    $this->assertDatabaseHas('business_functions', ['id' => $target->id, 'manager_id' => null]);
});

it('update: PATCH type re-maps the booleans', function () {
    $actor = businessFunctionUserWith(['update']);
    $target = BusinessFunction::factory()->create([
        'is_business_unit' => true,
        'is_business_service' => false,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/business-functions/{$target->id}", ['type' => 'business_service'])
        ->assertOk()
        ->assertJsonPath('data.is_business_unit', false)
        ->assertJsonPath('data.is_business_service', true);
});

it('update: 422 when the submitted type is invalid', function () {
    $actor = businessFunctionUserWith(['update']);
    $target = BusinessFunction::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/business-functions/{$target->id}", ['type' => 'bogus'])
        ->assertStatus(422)->assertJsonValidationErrors('type');
});

it('update: 403 without business-functions.update', function () {
    $actor = businessFunctionUserWith([]);
    $target = BusinessFunction::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/business-functions/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent function', function () {
    $actor = businessFunctionUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/business-functions/999999', ['name' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/business-functions/{businessFunction} (AC-012)
// ---------------------------------------------------------------------------

it('delete: 204, removes the function and cleans up the pivot rows', function () {
    $actor = businessFunctionUserWith(['delete']);
    $target = BusinessFunction::factory()->create();
    $member = User::factory()->create();
    $target->users()->sync([$member->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/business-functions/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('business_functions', ['id' => $target->id]);
    $this->assertDatabaseMissing('business_function_user', ['business_function_id' => $target->id]);
});

it('delete: 403 without business-functions.delete', function () {
    $actor = businessFunctionUserWith([]);
    $target = BusinessFunction::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/business-functions/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent function', function () {
    $actor = businessFunctionUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/business-functions/999999')->assertNotFound();
});
