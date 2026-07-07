<?php

use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('sourceUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function sourceUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("sources.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("sources.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/sources/{source} (AC-002/AC-003)
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = sourceUserWith(['view']);
    $target = Source::factory()->create(['name' => 'Website']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/sources/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Website');

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without sources.view', function () {
    $actor = sourceUserWith([]);
    $target = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/sources/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent source', function () {
    $actor = sourceUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/sources/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/sources (AC-002)
// ---------------------------------------------------------------------------

it('create: 201 + persists', function () {
    $actor = sourceUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/sources', ['name' => 'Referral'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Referral');

    $this->assertDatabaseHas('sources', ['name' => 'Referral']);
});

it('create: 403 without sources.create', function () {
    $actor = sourceUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/sources', ['name' => 'Nope'])->assertForbidden();
});

it('create: 422 when name is missing', function () {
    $actor = sourceUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/sources', [])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when name exceeds 191 characters', function () {
    $actor = sourceUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/sources', ['name' => str_repeat('a', 192)])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/sources/{source} (AC-002)
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the source', function () {
    $actor = sourceUserWith(['update']);
    $target = Source::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sources/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('sources', ['id' => $target->id, 'name' => 'After']);
});

it('update: 403 without sources.update', function () {
    $actor = sourceUserWith([]);
    $target = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sources/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent source', function () {
    $actor = sourceUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/sources/999999', ['name' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/sources/{source} (AC-002)
// ---------------------------------------------------------------------------

it('delete: 204, removes the source', function () {
    $actor = sourceUserWith(['delete']);
    $target = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/sources/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('sources', ['id' => $target->id]);
});

it('delete: 403 without sources.delete', function () {
    $actor = sourceUserWith([]);
    $target = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/sources/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent source', function () {
    $actor = sourceUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/sources/999999')->assertNotFound();
});
