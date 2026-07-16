<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * A `users.*` actor with the given subset of viewAny/view/viewActivity.
 * Self-contained (not the codebase-wide `userWithUserAbilities` helper) so the
 * `viewActivity` permission it findOrCreate's is never at the mercy of load
 * order across test files declaring a same-named guarded function.
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('activityLogActor')) {
    function activityLogActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'viewActivity'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            $actor->givePermissionTo("users.{$ability}");
        }

        return $actor;
    }
}

if (! function_exists('activityLogRowsPayload')) {
    /**
     * @return array<string, mixed>
     */
    function activityLogRowsPayload(): array
    {
        return ['startRow' => 0, 'endRow' => 25];
    }
}

// ---------------------------------------------------------------------------
// GET /api/activity-log/{resource}/{id} — envelope + authorization + 404
// ---------------------------------------------------------------------------

it('200 with the frozen envelope for an actor with users.view + users.viewActivity (AC-001)', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'message', 'data' => ['items', 'next_cursor']]);
});

it('403 without users.viewActivity (AC-002)', function () {
    $actor = activityLogActor(['view']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/users/{$target->id}")->assertForbidden();
});

it('403 with users.viewActivity but without users.view on the record (AC-003)', function () {
    $actor = activityLogActor(['viewActivity']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/users/{$target->id}")->assertForbidden();
});

it('404 for a resource not registered in config/activity-log.php, without leaking internals (AC-004)', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/activity-log/foo/1')->assertNotFound();

    expect($response->json('success'))->toBeFalse()
        ->and($response->json())->not->toHaveKey('exception');
});

it('404 for a non-existent record id on a registered resource (AC-004)', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/activity-log/users/999999')->assertNotFound();
});

it('422 when per_page is out of the 1..100 range', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/users/{$target->id}?per_page=0")->assertStatus(422);
    $this->getJson("/api/activity-log/users/{$target->id}?per_page=101")->assertStatus(422);
});

it('422 for a malformed cursor', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/users/{$target->id}?cursor=not-a-valid-cursor!!")->assertStatus(422);
});

// ---------------------------------------------------------------------------
// permissions:sync + detail envelope (AC-012)
// ---------------------------------------------------------------------------

it('permissions:sync creates users.viewActivity and the detail envelope reflects it per actor (AC-012)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    expect(Permission::where('name', 'users.viewActivity')->exists())->toBeTrue();

    $withAbility = activityLogActor(['view', 'viewActivity']);
    Sanctum::actingAs($withAbility);

    $this->getJson("/api/users/{$withAbility->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_activity', true);

    $withoutAbility = activityLogActor(['view']);
    Sanctum::actingAs($withoutAbility);

    $this->getJson("/api/users/{$withoutAbility->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_activity', false);
});

// ---------------------------------------------------------------------------
// Row-action `activity` — POST /api/tables/users/rows (AC-013)
// ---------------------------------------------------------------------------

it('row-action "activity" is present on every row when the actor has users.viewActivity (AC-013)', function () {
    $actor = activityLogActor(['viewAny', 'view', 'viewActivity']);
    User::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/users/rows', activityLogRowsPayload())->assertOk()->json('items');

    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        expect($row['actions'])->toContain('activity');
    }
});

it('row-action "activity" is absent without users.viewActivity (AC-013)', function () {
    $actor = activityLogActor(['viewAny', 'view']);
    User::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/users/rows', activityLogRowsPayload())->assertOk()->json('items');

    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        expect($row['actions'])->not->toContain('activity');
    }
});
