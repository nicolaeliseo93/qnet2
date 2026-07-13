<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productCategoryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productCategoryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("product-categories.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("product-categories.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/product-categories/for-select')->assertUnauthorized();
});

it('forbids actors without product-categories.viewAny (403)', function () {
    $actor = productCategoryUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/for-select')->assertForbidden();
});

it('allows actors with product-categories.viewAny (200) and returns the paginated envelope', function () {
    $actor = productCategoryUserWith(['viewAny']);
    ProductCategory::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/for-select')
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

it('maps a product category to { id, label: name }', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $target = ProductCategory::factory()->create(['name' => 'Office Supplies']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select?search=Office Supplies')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Office Supplies'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

it('searches by name', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $match = ProductCategory::factory()->create(['name' => 'Alphonse Target']);
    ProductCategory::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/product-categories/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = productCategoryUserWith(['viewAny']);
    $searchMatch = ProductCategory::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = ProductCategory::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/product-categories/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = productCategoryUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/product-categories/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});
