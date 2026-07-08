<?php

use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentForSelectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentForSelectUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referents.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-004 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/referents/for-select')->assertUnauthorized();
});

it('forbids actors without referents.viewAny (403)', function () {
    $actor = referentForSelectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/referents/for-select')->assertForbidden();
});

it('allows actors with referents.viewAny (200) and returns the paginated envelope', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    Referent::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/referents/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-004 — item shape + search + route order
// ---------------------------------------------------------------------------

it('maps a referent to { id, label: name }', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    $target = Referent::factory()->create(['name' => 'Ada Lovelace']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/referents/for-select?search=Ada Lovelace')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Ada Lovelace'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

it('searches by name', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    $match = Referent::factory()->create(['name' => 'Alphonse Target']);
    Referent::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/referents/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    $searchMatch = Referent::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = Referent::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/referents/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('resolves the literal for-select segment BEFORE the {referent} wildcard', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    // A non-numeric "id" would 404/fail model binding if the wildcard route
    // matched first; the literal for-select controller must win.
    $this->getJson('/api/referents/for-select')->assertOk();
});
