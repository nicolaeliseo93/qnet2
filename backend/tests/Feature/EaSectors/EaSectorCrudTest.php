<?php

use App\Models\EaSector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('eaSectorUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function eaSectorUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("ea-sectors.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("ea-sectors.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/ea-sectors/{eaSector} (AC-006)
// ---------------------------------------------------------------------------

it('show: 200 with parent summary and permissions block', function () {
    $actor = eaSectorUserWith(['view']);
    $root = EaSector::factory()->create(['name' => 'Root']);
    $child = EaSector::factory()->childOf($root)->create(['name' => 'Child']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/ea-sectors/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Child')
        ->assertJsonPath('data.parent.name', 'Root');

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without ea-sectors.view', function () {
    $actor = eaSectorUserWith([]);
    $target = EaSector::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/ea-sectors/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent sector', function () {
    $actor = eaSectorUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/ea-sectors/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/ea-sectors (AC-003, AC-004, AC-005, AC-006)
// ---------------------------------------------------------------------------

it('create: 201, root sector with parent_id null → data.parent is null', function () {
    $actor = eaSectorUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/ea-sectors', ['name' => 'Energy'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Energy')
        ->assertJsonPath('data.parent', null);

    $this->assertDatabaseHas('ea_sectors', ['name' => 'Energy', 'parent_id' => null]);
});

it('create: 201, child sector with an existing parent_id → data.parent = {id,name}', function () {
    $actor = eaSectorUserWith(['create']);
    $root = EaSector::factory()->create(['name' => 'Root']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/ea-sectors', ['name' => 'Child', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Child')
        ->assertJsonPath('data.parent', ['id' => $root->id, 'name' => 'Root']);
});

it('create: 422 without name, 422 with a non-existent parent_id', function () {
    $actor = eaSectorUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/ea-sectors', [])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    $this->postJson('/api/ea-sectors', ['name' => 'X', 'parent_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('parent_id');
});

it('create: 403 without ea-sectors.create', function () {
    $actor = eaSectorUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/ea-sectors', ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/ea-sectors/{eaSector} (AC-007, AC-008)
// ---------------------------------------------------------------------------

it('update: 200, partial name change persists', function () {
    $actor = eaSectorUserWith(['update']);
    $sector = EaSector::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$sector->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('ea_sectors', ['id' => $sector->id, 'name' => 'After']);
});

it('update: moves the sector under a new parent', function () {
    $actor = eaSectorUserWith(['update']);
    $oldParent = EaSector::factory()->create();
    $newParent = EaSector::factory()->create(['name' => 'NewParent']);
    $sector = EaSector::factory()->childOf($oldParent)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$sector->id}", ['parent_id' => $newParent->id])
        ->assertOk()
        ->assertJsonPath('data.parent.name', 'NewParent');

    expect($sector->fresh()->parent_id)->toBe($newParent->id);
});

it('update: parent_id = null promotes the sector to root', function () {
    $actor = eaSectorUserWith(['update']);
    $parent = EaSector::factory()->create();
    $sector = EaSector::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$sector->id}", ['parent_id' => null])
        ->assertOk()
        ->assertJsonPath('data.parent', null);

    expect($sector->fresh()->parent_id)->toBeNull();
});

it('update: parent_id = self → 422 (anti-cycle)', function () {
    $actor = eaSectorUserWith(['update']);
    $sector = EaSector::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$sector->id}", ['parent_id' => $sector->id])
        ->assertStatus(422);
});

it('update: parent_id = own descendant → 422 (anti-cycle), no write', function () {
    $actor = eaSectorUserWith(['update']);
    $root = EaSector::factory()->create();
    $child = EaSector::factory()->childOf($root)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$root->id}", ['parent_id' => $child->id])
        ->assertStatus(422);

    expect($root->fresh()->parent_id)->toBeNull();
});

it('update: 403 without ea-sectors.update', function () {
    $actor = eaSectorUserWith([]);
    $target = EaSector::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent sector', function () {
    $actor = eaSectorUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/ea-sectors/999999', ['name' => 'Nope'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/ea-sectors/{eaSector} (AC-009, AC-010, AC-006)
// ---------------------------------------------------------------------------

it('delete: 204 when the sector is a leaf (no children)', function () {
    $actor = eaSectorUserWith(['delete']);
    $target = EaSector::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/ea-sectors/{$target->id}")->assertNoContent();
    $this->assertDatabaseMissing('ea_sectors', ['id' => $target->id]);
});

it('delete: 409 with envelope {success:false,message} when it has children, row not removed', function () {
    $actor = eaSectorUserWith(['delete']);
    $parent = EaSector::factory()->create();
    EaSector::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $response = $this->deleteJson("/api/ea-sectors/{$parent->id}")->assertStatus(409);

    expect($response->json('success'))->toBeFalse()
        ->and($response->json('message'))->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('ea_sectors', ['id' => $parent->id]);
});

it('delete: 403 without ea-sectors.delete', function () {
    $actor = eaSectorUserWith([]);
    $target = EaSector::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/ea-sectors/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent sector', function () {
    $actor = eaSectorUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/ea-sectors/999999')->assertNotFound();
});
