<?php

use App\Enums\AttributeType;
use App\Models\Attribute;
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

if (! function_exists('productGenericFields')) {
    /**
     * The now-required generic fields (cost/price/product_type) a valid create
     * must carry, merged into each create call's payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function productGenericFields(array $overrides = []): array
    {
        return array_merge(['cost' => 10, 'price' => 20, 'product_type' => 'SERVICE'], $overrides);
    }
}

/**
 * A category carrying one attribute of each data type, all optional except
 * the caller-flagged required one.
 */
if (! function_exists('categoryWithOneOfEachAttributeType')) {
    /**
     * @return array{category: ProductCategory, string: Attribute, integer: Attribute, decimal: Attribute, boolean: Attribute, enum: Attribute}
     */
    function categoryWithOneOfEachAttributeType(?string $requiredKey = null, string $prefix = ''): array
    {
        $category = ProductCategory::factory()->create();

        $definitions = [
            'string' => Attribute::factory()->create(['code' => $prefix.'material', 'data_type' => AttributeType::String]),
            'integer' => Attribute::factory()->integer()->create(['code' => $prefix.'warranty']),
            'decimal' => Attribute::factory()->decimal()->create(['code' => $prefix.'weight']),
            'boolean' => Attribute::factory()->boolean()->create(['code' => $prefix.'waterproof']),
            'enum' => Attribute::factory()->enum(2)->create(['code' => $prefix.'color']),
        ];

        foreach ($definitions as $key => $attribute) {
            $category->attributes()->attach($attribute->id, ['is_required' => $key === $requiredKey, 'sort_order' => 0]);
        }

        return array_merge(['category' => $category], $definitions);
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/products/{product}
// ---------------------------------------------------------------------------

it('show: 200 with category summary and typed hydrated attributes', function () {
    $actor = productUserWith(['view']);
    ['category' => $category, 'integer' => $integer, 'enum' => $enum] = categoryWithOneOfEachAttributeType();
    $option = $enum->options->first();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $product->attributeValues()->create(['attribute_id' => $integer->id, 'value_integer' => 24]);
    $product->attributeValues()->create(['attribute_id' => $enum->id, 'option_id' => $option->id]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.category.id', $category->id);

    $attributes = collect($response->json('data.attributes'))->keyBy('code');
    expect($attributes['warranty']['value'])->toBe(24)
        ->and($attributes['color']['value'])->toBe($option->value)
        ->and($attributes['color']['option_id'])->toBe($option->id);
    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without products.view / 404 for a non-existent product', function () {
    $actor = productUserWith([]);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);
    $this->getJson("/api/products/{$product->id}")->assertForbidden();

    $actor = productUserWith(['view']);
    Sanctum::actingAs($actor);
    $this->getJson('/api/products/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/products (AC-014, AC-015)
// ---------------------------------------------------------------------------

it('create: 201, every value routed to its typed column', function () {
    $actor = productUserWith(['create']);
    ['category' => $category, 'string' => $string, 'integer' => $integer, 'decimal' => $decimal, 'boolean' => $boolean, 'enum' => $enum] = categoryWithOneOfEachAttributeType();
    $option = $enum->options->first();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields([
        'name' => 'Widget', 'category_id' => $category->id,
        'attributes' => [
            ['attribute_id' => $string->id, 'value' => 'nylon'],
            ['attribute_id' => $integer->id, 'value' => 12],
            ['attribute_id' => $decimal->id, 'value' => 1.25],
            ['attribute_id' => $boolean->id, 'value' => true],
            ['attribute_id' => $enum->id, 'value' => $option->value],
        ],
    ]))->assertCreated();

    $product = Product::where('name', 'Widget')->firstOrFail();
    expect($product->product_type->value)->toBe('SERVICE')
        ->and((float) $product->cost)->toBe(10.0)
        ->and((float) $product->price)->toBe(20.0);
    $this->assertDatabaseHas('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $string->id, 'value_string' => 'nylon']);
    $this->assertDatabaseHas('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $integer->id, 'value_integer' => 12]);
    $this->assertDatabaseHas('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $decimal->id, 'value_decimal' => 1.25]);
    $this->assertDatabaseHas('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $boolean->id, 'value_boolean' => 1]);
    $this->assertDatabaseHas('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $enum->id, 'option_id' => $option->id]);
});

it('create: attribute not in the category effective attributes → 422', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    $foreignAttribute = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields([
        'name' => 'X', 'category_id' => $category->id,
        'attributes' => [['attribute_id' => $foreignAttribute->id, 'value' => 'x']],
    ]))->assertStatus(422);
});

it('create: value incoherent with the data_type → 422', function () {
    $actor = productUserWith(['create']);
    ['category' => $category, 'integer' => $integer] = categoryWithOneOfEachAttributeType();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields([
        'name' => 'X', 'category_id' => $category->id,
        'attributes' => [['attribute_id' => $integer->id, 'value' => 'not-a-number']],
    ]))->assertStatus(422);
});

it('create: ENUM value outside the options → 422', function () {
    $actor = productUserWith(['create']);
    ['category' => $category, 'enum' => $enum] = categoryWithOneOfEachAttributeType();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields([
        'name' => 'X', 'category_id' => $category->id,
        'attributes' => [['attribute_id' => $enum->id, 'value' => 'not-an-option']],
    ]))->assertStatus(422);
});

it('create: a required attribute missing → 422', function () {
    $actor = productUserWith(['create']);
    ['category' => $category] = categoryWithOneOfEachAttributeType(requiredKey: 'integer');
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields(['name' => 'X', 'category_id' => $category->id]))->assertStatus(422);
});

it('create: 403 without products.create / 422 without the required generic fields', function () {
    $category = ProductCategory::factory()->create();

    $actor = productUserWith([]);
    Sanctum::actingAs($actor);
    $this->postJson('/api/products', productGenericFields(['name' => 'X', 'category_id' => $category->id]))->assertForbidden();

    $actor = productUserWith(['create']);
    Sanctum::actingAs($actor);
    $this->postJson('/api/products', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'category_id', 'cost', 'price', 'product_type']);
});

it('create: invalid product_type → 422', function () {
    $actor = productUserWith(['create']);
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/products', productGenericFields([
        'name' => 'X', 'category_id' => $category->id, 'product_type' => 'GOODS',
    ]))->assertStatus(422)->assertJsonValidationErrors('product_type');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/products/{product} (AC-017)
// ---------------------------------------------------------------------------

it('update: attributes is a full-replace of the dynamic values', function () {
    $actor = productUserWith(['update']);
    ['category' => $category, 'string' => $string, 'integer' => $integer] = categoryWithOneOfEachAttributeType();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $product->attributeValues()->create(['attribute_id' => $string->id, 'value_string' => 'old']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", [
        'attributes' => [['attribute_id' => $integer->id, 'value' => 7]],
    ])->assertOk();

    expect($product->fresh()->attributeValues->pluck('attribute_id')->all())->toBe([$integer->id]);
});

it('update: changing category_id prunes stale values and requires the new required set', function () {
    $actor = productUserWith(['update']);
    ['category' => $oldCategory, 'string' => $oldStringAttribute] = categoryWithOneOfEachAttributeType(prefix: 'old_');
    ['category' => $newCategory, 'integer' => $newRequiredAttribute] = categoryWithOneOfEachAttributeType(requiredKey: 'integer', prefix: 'new_');
    $product = Product::factory()->create(['category_id' => $oldCategory->id]);
    $product->attributeValues()->create(['attribute_id' => $oldStringAttribute->id, 'value_string' => 'old']);
    Sanctum::actingAs($actor);

    // Missing the new category's required attribute → 422, nothing pruned yet.
    $this->patchJson("/api/products/{$product->id}", ['category_id' => $newCategory->id])->assertStatus(422);
    expect($product->fresh()->attributeValues)->toHaveCount(1);

    // Same request but with the new required value present → succeeds, old value pruned.
    $product->attributeValues()->create(['attribute_id' => $newRequiredAttribute->id, 'value_integer' => 12]);
    $this->patchJson("/api/products/{$product->id}", ['category_id' => $newCategory->id])->assertOk();

    $remaining = $product->fresh()->attributeValues;
    expect($remaining->pluck('attribute_id')->all())->toBe([$newRequiredAttribute->id]);
});

it('update: product_type patch is validated against the enum (invalid → 422, valid → 200)', function () {
    $actor = productUserWith(['update']);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['product_type' => 'GOODS'])
        ->assertStatus(422)->assertJsonValidationErrors('product_type');

    $this->patchJson("/api/products/{$product->id}", ['product_type' => 'SERVICE'])->assertOk();
    expect($product->fresh()->product_type->value)->toBe('SERVICE');
});

it('update: 403 without products.update / 404 for a non-existent product', function () {
    $actor = productUserWith([]);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);
    $this->patchJson("/api/products/{$product->id}", ['name' => 'Nope'])->assertForbidden();

    $actor = productUserWith(['update']);
    Sanctum::actingAs($actor);
    $this->patchJson('/api/products/999999', ['name' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/products/{product}
// ---------------------------------------------------------------------------

it('delete: 204, cascades product_attribute_values', function () {
    $actor = productUserWith(['delete']);
    $product = Product::factory()->create();
    $attribute = Attribute::factory()->create();
    $product->attributeValues()->create(['attribute_id' => $attribute->id, 'value_string' => 'x']);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
    $this->assertDatabaseMissing('product_attribute_values', ['product_id' => $product->id]);
});

it('delete: 403 without products.delete', function () {
    $actor = productUserWith([]);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/products/{$product->id}")->assertForbidden();
});
