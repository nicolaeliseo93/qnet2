<?php

use App\Models\EaSector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('eaSectorForSelectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function eaSectorForSelectUserWith(array $abilities): User
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
// AC-005 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/ea-sectors/for-select')->assertUnauthorized();
});

it('forbids actors without ea-sectors.viewAny (403)', function () {
    $actor = eaSectorForSelectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/ea-sectors/for-select')->assertForbidden();
});

it('allows actors with ea-sectors.viewAny (200) and returns the paginated envelope', function () {
    $actor = eaSectorForSelectUserWith(['viewAny']);
    EaSector::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/ea-sectors/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-005 — item shape + search + route order
// ---------------------------------------------------------------------------

it('maps an EA sector to { id, label: name }', function () {
    $actor = eaSectorForSelectUserWith(['viewAny']);
    $target = EaSector::factory()->create(['name' => 'Renewable Energy']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/ea-sectors/for-select?search=Renewable Energy')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Renewable Energy'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

it('searches by name', function () {
    $actor = eaSectorForSelectUserWith(['viewAny']);
    $match = EaSector::factory()->create(['name' => 'Alphonse Target']);
    EaSector::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/ea-sectors/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

it('resolves the literal for-select segment BEFORE the {eaSector} wildcard', function () {
    $actor = eaSectorForSelectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/ea-sectors/for-select')->assertOk();
});

it('resolves the literal tree segment independently of for-select (both above the wildcard)', function () {
    $actor = eaSectorForSelectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/ea-sectors/tree')->assertOk();
    $this->getJson('/api/ea-sectors/for-select')->assertOk();
});
