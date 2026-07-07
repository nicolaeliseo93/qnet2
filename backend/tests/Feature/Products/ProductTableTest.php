<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("products.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("products.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-018 — columns config
// ---------------------------------------------------------------------------

it('returns the 6 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = productUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/products/columns')->assertForbidden();

    $actor = productUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/products/columns')->assertOk()->json('data');

    expect($data['resource'])->toBe('products')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['name', 'description', 'cost', 'price', 'category', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['description']['sortable'])->toBeFalse()
        ->and($columns['category']['filterType'])->toBe('set');
});

// ---------------------------------------------------------------------------
// AC-018 — rows shape (no N+1: category eager-loaded)
// ---------------------------------------------------------------------------

it('rows expose id/name/description/cost/price/category{id,name}/created_at + per-row actions', function () {
    $actor = productUserWith(['viewAny', 'view', 'update', 'delete']);
    $category = ProductCategory::factory()->create(['name' => 'Electronics']);
    Product::factory()->create(['name' => 'Gadget', 'category_id' => $category->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Gadget');

    expect($row)->not->toBeNull()
        ->and($row['category'])->toBe(['id' => $category->id, 'name' => 'Electronics'])
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('rows: no N+1 on the category relation', function () {
    $actor = productUserWith(['viewAny']);
    Product::factory()->count(5)->create();
    Sanctum::actingAs($actor);

    Product::preventLazyLoading();

    $this->postJson('/api/tables/products/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    Product::preventLazyLoading(false);
});

// ---------------------------------------------------------------------------
// AC-018 — values endpoint (derived `category` set filter)
// ---------------------------------------------------------------------------

it('values: category → distinct category names, columnId outside the allow-list → 422', function () {
    $actor = productUserWith(['viewAny']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Electronics']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Clothing']);
    Product::factory()->create(['category_id' => $categoryA->id]);
    Product::factory()->create(['category_id' => $categoryB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/values', ['columnId' => 'category'])->assertOk();
    expect($response->json('data.values'))->toEqualCanonicalizing(['Electronics', 'Clothing']);

    $this->postJson('/api/tables/products/values', ['columnId' => 'not_a_column'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

it('values: category search narrows the distinct list', function () {
    $actor = productUserWith(['viewAny']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Electronics']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Clothing']);
    Product::factory()->create(['category_id' => $categoryA->id]);
    Product::factory()->create(['category_id' => $categoryB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/values', ['columnId' => 'category', 'search' => 'Elec'])->assertOk();

    expect($response->json('data.values'))->toBe(['Electronics']);
});

it('sort: rows ordered by the derived category name', function () {
    $actor = productUserWith(['viewAny']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Zebra Category']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Alpha Category']);
    Product::factory()->create(['name' => 'FromZebra', 'category_id' => $categoryA->id]);
    Product::factory()->create(['name' => 'FromAlpha', 'category_id' => $categoryB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'category', 'sort' => 'asc']],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['FromAlpha', 'FromZebra']);
});

it('filter: category set filter narrows the rows via whereHas', function () {
    $actor = productUserWith(['viewAny']);
    $categoryA = ProductCategory::factory()->create(['name' => 'Electronics']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Clothing']);
    Product::factory()->create(['name' => 'Laptop', 'category_id' => $categoryA->id]);
    Product::factory()->create(['name' => 'Shirt', 'category_id' => $categoryB->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/products/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['category' => ['filterType' => 'set', 'values' => ['Electronics']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Laptop']);
});
