<?php

use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('sectorUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function sectorUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("sectors.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("sectors.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/sectors/{sector} (AC-006)
// ---------------------------------------------------------------------------

it('show: 200 with parent summary and permissions block', function () {
    $actor = sectorUserWith(['view']);
    $root = Sector::factory()->create(['name' => 'Root']);
    $child = Sector::factory()->childOf($root)->create(['name' => 'Child']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/sectors/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Child')
        ->assertJsonPath('data.parent.name', 'Root');

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without sectors.view', function () {
    $actor = sectorUserWith([]);
    $target = Sector::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/sectors/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent sector', function () {
    $actor = sectorUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/sectors/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/sectors (AC-003, AC-004, AC-005, AC-006)
// ---------------------------------------------------------------------------

it('create: 201, root sector with parent_id null → data.parent is null', function () {
    $actor = sectorUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/sectors', ['name' => 'Energy'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Energy')
        ->assertJsonPath('data.parent', null);

    $this->assertDatabaseHas('sectors', ['name' => 'Energy', 'parent_id' => null]);
});

it('create: 201, child sector with an existing parent_id → data.parent = {id,name}', function () {
    $actor = sectorUserWith(['create']);
    $root = Sector::factory()->create(['name' => 'Root']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/sectors', ['name' => 'Child', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Child')
        ->assertJsonPath('data.parent', ['id' => $root->id, 'name' => 'Root']);
});

it('create: 422 without name, 422 with a non-existent parent_id', function () {
    $actor = sectorUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/sectors', [])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    $this->postJson('/api/sectors', ['name' => 'X', 'parent_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('parent_id');
});

it('create: 403 without sectors.create', function () {
    $actor = sectorUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/sectors', ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/sectors/{sector} (AC-007, AC-008)
// ---------------------------------------------------------------------------

it('update: 200, partial name change persists', function () {
    $actor = sectorUserWith(['update']);
    $sector = Sector::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sectors/{$sector->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('sectors', ['id' => $sector->id, 'name' => 'After']);
});

it('update: moves the sector under a new parent', function () {
    $actor = sectorUserWith(['update']);
    $oldParent = Sector::factory()->create();
    $newParent = Sector::factory()->create(['name' => 'NewParent']);
    $sector = Sector::factory()->childOf($oldParent)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sectors/{$sector->id}", ['parent_id' => $newParent->id])
        ->assertOk()
        ->assertJsonPath('data.parent.name', 'NewParent');

    expect($sector->fresh()->parent_id)->toBe($newParent->id);
});

it('update: parent_id = null promotes the sector to root', function () {
    $actor = sectorUserWith(['update']);
    $parent = Sector::factory()->create();
    $sector = Sector::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sectors/{$sector->id}", ['parent_id' => null])
        ->assertOk()
        ->assertJsonPath('data.parent', null);

    expect($sector->fresh()->parent_id)->toBeNull();
});

it('update: parent_id = self → 422 (anti-cycle)', function () {
    $actor = sectorUserWith(['update']);
    $sector = Sector::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sectors/{$sector->id}", ['parent_id' => $sector->id])
        ->assertStatus(422);
});

it('update: parent_id = own descendant → 422 (anti-cycle), no write', function () {
    $actor = sectorUserWith(['update']);
    $root = Sector::factory()->create();
    $child = Sector::factory()->childOf($root)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sectors/{$root->id}", ['parent_id' => $child->id])
        ->assertStatus(422);

    expect($root->fresh()->parent_id)->toBeNull();
});

it('update: 403 without sectors.update', function () {
    $actor = sectorUserWith([]);
    $target = Sector::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sectors/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent sector', function () {
    $actor = sectorUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/sectors/999999', ['name' => 'Nope'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/sectors/{sector} (AC-009, AC-010, AC-006)
// ---------------------------------------------------------------------------

it('delete: 204 when the sector is a leaf (no children)', function () {
    $actor = sectorUserWith(['delete']);
    $target = Sector::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/sectors/{$target->id}")->assertNoContent();
    $this->assertDatabaseMissing('sectors', ['id' => $target->id]);
});

it('delete: 409 with envelope {success:false,message} when it has children, row not removed', function () {
    $actor = sectorUserWith(['delete']);
    $parent = Sector::factory()->create();
    Sector::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $response = $this->deleteJson("/api/sectors/{$parent->id}")->assertStatus(409);

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('sectors', ['id' => $parent->id]);
});

it('delete: 403 without sectors.delete', function () {
    $actor = sectorUserWith([]);
    $target = Sector::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/sectors/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent sector', function () {
    $actor = sectorUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/sectors/999999')->assertNotFound();
});
