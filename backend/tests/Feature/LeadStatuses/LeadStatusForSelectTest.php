<?php

use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("lead-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("lead-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-006 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/lead-statuses/for-select')->assertUnauthorized();
});

it('forbids actors without lead-statuses.viewAny (403)', function () {
    $actor = leadStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/lead-statuses/for-select')->assertForbidden();
});

it('allows actors with lead-statuses.viewAny (200) and returns the paginated envelope', function () {
    $actor = leadStatusUserWith(['viewAny']);
    LeadStatus::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/lead-statuses/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-008 — item shape + sort_order-first ordering + search
// ---------------------------------------------------------------------------

it('maps a lead status to { id, label: name, meta: { system_key } } (spec 0039, D-2)', function () {
    $actor = leadStatusUserWith(['viewAny']);
    $target = LeadStatus::factory()->create(['name' => 'In Progress']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/lead-statuses/for-select?search=In Progress')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    // requirement changed (spec 0039, AC-009): `meta.system_key` is a new,
    // always-present key (null for a custom row) — was {id, label} only.
    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'In Progress', 'meta' => ['system_key' => null]])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'meta']);
});

it('orders by sort_order asc, not alphabetically (AC-008)', function () {
    $actor = leadStatusUserWith(['viewAny']);
    // sort_order 100/101 (not 0/10) so this pair never collides with the two
    // migration-seeded system rows ("Nuovo" sort_order 0, "Chiuso" 10).
    $zLast = LeadStatus::factory()->create(['name' => 'Zeta', 'sort_order' => 100]);
    $aFirst = LeadStatus::factory()->create(['name' => 'Alpha', 'sort_order' => 101]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/lead-statuses/for-select')->assertOk();
    // Filtered to this pair (preserving response order): the endpoint also
    // returns the two system rows, irrelevant to this ordering assertion.
    $ids = collect($response->json('items'))
        ->pluck('id')
        ->filter(fn (int $id): bool => in_array($id, [$zLast->id, $aFirst->id], true))
        ->values()
        ->all();

    expect($ids)->toBe([$zLast->id, $aFirst->id]);
});

it('search="qual" returns only names containing "qual" (AC-008)', function () {
    $actor = leadStatusUserWith(['viewAny']);
    $match = LeadStatus::factory()->create(['name' => 'Qualified']);
    LeadStatus::factory()->create(['name' => 'Lost']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/lead-statuses/for-select?search=qual')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-008 — ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = leadStatusUserWith(['viewAny']);
    $searchMatch = LeadStatus::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = LeadStatus::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/lead-statuses/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = leadStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/lead-statuses/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});
