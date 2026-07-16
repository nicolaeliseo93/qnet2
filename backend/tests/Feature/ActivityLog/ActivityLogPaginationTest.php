<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
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

/**
 * Toggles `is_active` `$count` times, guaranteeing a genuinely dirty (and
 * therefore logged) update every time regardless of the starting value.
 */
if (! function_exists('toggleActivityLogTarget')) {
    function toggleActivityLogTarget(User $target, int $count): void
    {
        $value = $target->is_active;

        for ($i = 0; $i < $count; $i++) {
            $value = ! $value;
            $target->update(['is_active' => $value]);
        }
    }
}

// ---------------------------------------------------------------------------
// Keyset pagination (AC-009)
// ---------------------------------------------------------------------------

it('paginates by keyset: bounded items, created_at/id desc order, no repeat or skip (AC-009)', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    $target = User::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    // 1 `created` (from the factory) + 6 `updated` = 7 activities total.
    toggleActivityLogTarget($target, 6);

    $page1 = $this->getJson("/api/activity-log/users/{$target->id}?per_page=3")->assertOk()->json('data');

    expect($page1['items'])->toHaveCount(3)
        ->and($page1['next_cursor'])->not->toBeNull();

    $page1Ids = collect($page1['items'])->pluck('id');
    expect($page1Ids->values()->all())->toBe($page1Ids->sortDesc()->values()->all());

    $page2 = $this->getJson("/api/activity-log/users/{$target->id}?per_page=3&cursor={$page1['next_cursor']}")
        ->assertOk()->json('data');
    $page2Ids = collect($page2['items']);

    expect($page2['items'])->toHaveCount(3)
        ->and($page2['next_cursor'])->not->toBeNull()
        ->and($page2Ids->pluck('id')->intersect($page1Ids))->toBeEmpty();

    $page3 = $this->getJson("/api/activity-log/users/{$target->id}?per_page=3&cursor={$page2['next_cursor']}")
        ->assertOk()->json('data');

    $seenIds = $page1Ids->merge($page2Ids->pluck('id'));

    // 7 total - 3 - 3 = 1 remaining, and this is the last page.
    expect($page3['items'])->toHaveCount(1)
        ->and($page3['next_cursor'])->toBeNull()
        ->and(collect($page3['items'])->pluck('id')->intersect($seenIds))->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Causer resolution without N+1 (AC-010)
// ---------------------------------------------------------------------------

it('resolves causer to {id, name} via a single batched query, never one per row (AC-010)', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    // Fixed starting locale (rather than the factory's random en/it draw) so
    // every toggle below is guaranteed dirty regardless of that draw — mirrors
    // toggleActivityLogTarget()'s same fix for the flakiness this caused
    // (verifier finding: a random starting 'it' could make the first update a
    // no-op, silently dropping "Causer A"'s entry via dontSubmitEmptyLogs()).
    $target = User::factory()->create(['locale' => 'en']);

    $causers = User::factory()->count(3)->sequence(
        ['name' => 'Causer A'],
        ['name' => 'Causer B'],
        ['name' => 'Causer C'],
    )->create();

    $locale = 'en';

    foreach ($causers as $causer) {
        $locale = $locale === 'en' ? 'it' : 'en';
        Sanctum::actingAs($causer);
        $target->update(['locale' => $locale]);
    }

    Sanctum::actingAs($actor);
    DB::enableQueryLog();
    $response = $this->getJson("/api/activity-log/users/{$target->id}")->assertOk();
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    // Every distinct causer is resolved by ONE batched `WHERE users.id IN
    // (...)` query (eager-loaded), never one SELECT per row — the N+1 this
    // AC guards against.
    $causerQueries = $queries->filter(
        fn (array $query): bool => str_contains($query['query'], 'from "users"') && str_contains($query['query'], ' in (')
    );
    expect($causerQueries)->toHaveCount(1);

    $causerNames = collect($response->json('data.items'))->pluck('causer.name')->filter()->unique();
    expect($causerNames)->toContain('Causer A', 'Causer B', 'Causer C');
});
