<?php

use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('registryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function registryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("registries.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/registries/for-select')->assertUnauthorized();
});

it('forbids actors without registries.viewAny (403)', function () {
    $actor = registryUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/registries/for-select')->assertForbidden();
});

it('allows actors with registries.viewAny (200) and returns the paginated envelope', function () {
    $actor = registryUserWith(['viewAny']);
    Registry::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/registries/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// item shape + search
// ---------------------------------------------------------------------------

it('maps a registry to { id, label: name }', function () {
    $actor = registryUserWith(['viewAny']);
    $target = Registry::factory()->create(['name' => 'Acme Supplies']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/registries/for-select?search=Acme Supplies')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Acme Supplies'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

it('searches by name', function () {
    $actor = registryUserWith(['viewAny']);
    $match = Registry::factory()->create(['name' => 'Alphonse Target']);
    Registry::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/registries/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = registryUserWith(['viewAny']);
    $searchMatch = Registry::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = Registry::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/registries/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = registryUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/registries/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});
