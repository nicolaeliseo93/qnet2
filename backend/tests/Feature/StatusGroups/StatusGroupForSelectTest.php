<?php

use App\Models\StatusGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('statusGroupUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function statusGroupUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("status-groups.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("status-groups.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-007 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/status-groups/for-select')->assertUnauthorized();
});

it('forbids actors without status-groups.viewAny (403)', function () {
    $actor = statusGroupUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/status-groups/for-select')->assertForbidden();
});

it('allows actors with status-groups.viewAny (200) and returns the paginated envelope', function () {
    $actor = statusGroupUserWith(['viewAny']);
    StatusGroup::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/status-groups/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// item shape + sort_order-first ordering + search
// ---------------------------------------------------------------------------

it('maps a status group to { id, label: name }', function () {
    $actor = statusGroupUserWith(['viewAny']);
    $target = StatusGroup::factory()->create(['name' => 'In Progress']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/status-groups/for-select?search=In Progress')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'In Progress'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

it('orders by sort_order asc, not alphabetically', function () {
    $actor = statusGroupUserWith(['viewAny']);
    // sort_order 100/101 (not 0/10) so this pair never collides with the two
    // migration-seeded system groups ("Aperto" sort_order 0, "Chiuso" 10).
    $zLast = StatusGroup::factory()->create(['name' => 'Zeta', 'sort_order' => 100]);
    $aFirst = StatusGroup::factory()->create(['name' => 'Alpha', 'sort_order' => 101]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/status-groups/for-select')->assertOk();
    // Filtered to this pair (preserving response order): the endpoint also
    // returns the two system groups, irrelevant to this ordering assertion.
    $ids = collect($response->json('items'))
        ->pluck('id')
        ->filter(fn (int $id): bool => in_array($id, [$zLast->id, $aFirst->id], true))
        ->values()
        ->all();

    expect($ids)->toBe([$zLast->id, $aFirst->id]);
});

it('search="qual" returns only names containing "qual"', function () {
    $actor = statusGroupUserWith(['viewAny']);
    $match = StatusGroup::factory()->create(['name' => 'Qualified']);
    StatusGroup::factory()->create(['name' => 'Lost']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/status-groups/for-select?search=qual')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = statusGroupUserWith(['viewAny']);
    $searchMatch = StatusGroup::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = StatusGroup::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/status-groups/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = statusGroupUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/status-groups/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});
