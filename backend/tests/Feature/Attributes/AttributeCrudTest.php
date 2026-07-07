<?php

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('attributeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function attributeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("attributes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("attributes.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/attributes/{attribute}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = attributeUserWith(['view']);
    $target = Attribute::factory()->enum(2)->create(['code' => 'color', 'name' => 'Color']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/attributes/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.code', 'color')
        ->assertJsonPath('data.data_type', 'ENUM');

    expect($response->json('data.options'))->toHaveCount(2);
    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without attributes.view', function () {
    $actor = attributeUserWith([]);
    $target = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/attributes/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent attribute', function () {
    $actor = attributeUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/attributes/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/attributes (AC-003)
// ---------------------------------------------------------------------------

it('create: 201 + persists a STRING attribute, ignoring any submitted options', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', [
        'code' => 'material', 'name' => 'Material', 'data_type' => 'STRING',
        'options' => [['value' => 'ignored', 'label' => 'Ignored']],
    ])->assertCreated()->assertJsonPath('data.code', 'material');

    $this->assertDatabaseHas('attributes', ['code' => 'material', 'data_type' => 'STRING']);
    expect(Attribute::where('code', 'material')->first()->options)->toBeEmpty();
});

it('create: ENUM without options → 422', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', ['code' => 'color', 'name' => 'Color', 'data_type' => 'ENUM'])
        ->assertStatus(422)->assertJsonValidationErrors('options');
});

it('create: ENUM with valid options → 201, options persisted with unique values', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/attributes', [
        'code' => 'color', 'name' => 'Color', 'data_type' => 'ENUM',
        'options' => [
            ['value' => 'red', 'label' => 'Red'],
            ['value' => 'blue', 'label' => 'Blue'],
        ],
    ])->assertCreated();

    expect($response->json('data.options'))->toHaveCount(2);
    $this->assertDatabaseHas('attribute_options', ['value' => 'red']);
    $this->assertDatabaseHas('attribute_options', ['value' => 'blue']);
});

it('create: ENUM with duplicate option values → 422', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', [
        'code' => 'color', 'name' => 'Color', 'data_type' => 'ENUM',
        'options' => [
            ['value' => 'red', 'label' => 'Red'],
            ['value' => 'red', 'label' => 'Red again'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('options');
});

it('create: 403 without attributes.create', function () {
    $actor = attributeUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', ['code' => 'x', 'name' => 'X', 'data_type' => 'STRING'])->assertForbidden();
});

it('create: 422 on duplicate code / invalid data_type', function () {
    $actor = attributeUserWith(['create']);
    Attribute::factory()->create(['code' => 'existing']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', ['code' => 'existing', 'name' => 'Dup', 'data_type' => 'STRING'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->postJson('/api/attributes', ['code' => 'new_code', 'name' => 'X', 'data_type' => 'NOT_A_TYPE'])
        ->assertStatus(422)->assertJsonValidationErrors('data_type');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/attributes/{attribute} (AC-004)
// ---------------------------------------------------------------------------

it('update: options is a full-replace', function () {
    $actor = attributeUserWith(['update']);
    $attribute = Attribute::factory()->enum(2)->create();
    $originalOptionIds = $attribute->options->pluck('id');
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/attributes/{$attribute->id}", [
        'options' => [['value' => 'green', 'label' => 'Green']],
    ])->assertOk();

    expect($response->json('data.options'))->toHaveCount(1)
        ->and($response->json('data.options.0.value'))->toBe('green');
    expect(AttributeOption::whereIn('id', $originalOptionIds)->count())->toBe(0);
});

it('update: changing data_type when the attribute already has product values → 422', function () {
    $actor = attributeUserWith(['update']);
    $attribute = Attribute::factory()->create(['data_type' => AttributeType::String]);
    $product = Product::factory()->create();
    ProductAttributeValue::factory()->for($product)->for($attribute, 'attribute')->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/attributes/{$attribute->id}", ['data_type' => 'INTEGER'])->assertStatus(422);

    expect($attribute->fresh()->data_type)->toBe(AttributeType::String);
});

it('update: 403 without attributes.update', function () {
    $actor = attributeUserWith([]);
    $target = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/attributes/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/attributes/{attribute} (AC-005)
// ---------------------------------------------------------------------------

it('delete: 204 when the attribute is unused', function () {
    $actor = attributeUserWith(['delete']);
    $target = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/attributes/{$target->id}")->assertNoContent();
    $this->assertDatabaseMissing('attributes', ['id' => $target->id]);
});

it('delete: 409 when assigned to a category', function () {
    $actor = attributeUserWith(['delete']);
    $target = Attribute::factory()->create();
    $category = ProductCategory::factory()->create();
    $category->attributes()->attach($target->id, ['is_required' => false, 'sort_order' => 0]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/attributes/{$target->id}")->assertStatus(409);
    $this->assertDatabaseHas('attributes', ['id' => $target->id]);
});

it('delete: 409 when it has recorded product values', function () {
    $actor = attributeUserWith(['delete']);
    $target = Attribute::factory()->create();
    $product = Product::factory()->create();
    ProductAttributeValue::factory()->for($product)->for($target, 'attribute')->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/attributes/{$target->id}")->assertStatus(409);
});

it('delete: 403 without attributes.delete', function () {
    $actor = attributeUserWith([]);
    $target = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/attributes/{$target->id}")->assertForbidden();
});
