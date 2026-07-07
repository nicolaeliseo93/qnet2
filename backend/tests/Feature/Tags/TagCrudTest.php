<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('tagUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function tagUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("tags.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tags.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/tags/{tag} (AC-002/AC-003)
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = tagUserWith(['view']);
    $target = Tag::factory()->create(['name' => 'VIP']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/tags/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'VIP');

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without tags.view', function () {
    $actor = tagUserWith([]);
    $target = Tag::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tags/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent tag', function () {
    $actor = tagUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tags/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/tags (AC-002)
// ---------------------------------------------------------------------------

it('create: 201 + persists', function () {
    $actor = tagUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tags', ['name' => 'Priority'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Priority');

    $this->assertDatabaseHas('tags', ['name' => 'Priority']);
});

it('create: 403 without tags.create', function () {
    $actor = tagUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tags', ['name' => 'Nope'])->assertForbidden();
});

it('create: 422 when name is missing', function () {
    $actor = tagUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tags', [])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when name exceeds 191 characters', function () {
    $actor = tagUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tags', ['name' => str_repeat('a', 192)])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/tags/{tag} (AC-002)
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the tag', function () {
    $actor = tagUserWith(['update']);
    $target = Tag::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tags/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('tags', ['id' => $target->id, 'name' => 'After']);
});

it('update: 403 without tags.update', function () {
    $actor = tagUserWith([]);
    $target = Tag::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tags/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent tag', function () {
    $actor = tagUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/tags/999999', ['name' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/tags/{tag} (AC-002)
// ---------------------------------------------------------------------------

it('delete: 204, removes the tag', function () {
    $actor = tagUserWith(['delete']);
    $target = Tag::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tags/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('tags', ['id' => $target->id]);
});

it('delete: 403 without tags.delete', function () {
    $actor = tagUserWith([]);
    $target = Tag::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tags/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent tag', function () {
    $actor = tagUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/tags/999999')->assertNotFound();
});
