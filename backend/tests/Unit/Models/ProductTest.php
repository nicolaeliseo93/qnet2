<?php

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\Concerns\LogsModelActivity;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-013 — schema
// ---------------------------------------------------------------------------

it('creates the products and product_attribute_values tables with the expected columns', function () {
    expect(Schema::hasTable('products'))->toBeTrue();
    expect(Schema::hasColumns('products', ['id', 'name', 'description', 'cost', 'price', 'category_id']))->toBeTrue();

    expect(Schema::hasTable('product_attribute_values'))->toBeTrue();
    expect(Schema::hasColumns('product_attribute_values', [
        'id', 'product_id', 'attribute_id', 'value_string', 'value_integer', 'value_decimal', 'value_boolean', 'option_id',
    ]))->toBeTrue();
});

it('a product_attribute_values row is unique per (product_id, attribute_id)', function () {
    $product = Product::factory()->create();
    $attribute = Attribute::factory()->create();

    ProductAttributeValue::factory()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id]);

    expect(fn () => ProductAttributeValue::factory()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id]))
        ->toThrow(QueryException::class);
});

it('a category with products cannot be deleted at the database level (restrictOnDelete)', function () {
    $category = ProductCategory::factory()->create();
    Product::factory()->create(['category_id' => $category->id]);

    expect(fn () => DB::table('product_categories')->where('id', $category->id)->delete())
        ->toThrow(QueryException::class);
});

it('down() reverses the products migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_07_110400_create_products_table.php');

    Schema::dropIfExists('product_attribute_values');
    $migration->down();

    expect(Schema::hasTable('products'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('products'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-013 — model relations, cast, activity log, morph map
// ---------------------------------------------------------------------------

it('category() is a BelongsTo relation and attributeValues() a HasMany', function () {
    $product = new Product;

    expect($product->category())->toBeInstanceOf(BelongsTo::class);
    expect($product->attributeValues())->toBeInstanceOf(HasMany::class);
});

it('deleting a product cascades to its attribute values', function () {
    $product = Product::factory()->create();
    $value = ProductAttributeValue::factory()->create(['product_id' => $product->id]);

    $product->delete();

    expect(ProductAttributeValue::find($value->id))->toBeNull();
});

it('logs model activity', function () {
    expect(class_uses(Product::class))->toHaveKey(LogsModelActivity::class);
});

it('registers the "product" morph alias', function () {
    expect(array_search(Product::class, Relation::morphMap(), true))->toBe('product');
});

// ---------------------------------------------------------------------------
// AC-014 — typed `value` accessor routes to the right column per data_type
// ---------------------------------------------------------------------------

it('the value accessor resolves the right column per attribute data_type', function () {
    $product = Product::factory()->create();

    $int = Attribute::factory()->integer()->create();
    $pav = ProductAttributeValue::factory()->for($product)->for($int, 'attribute')->integer(42)->create();
    expect($pav->fresh(['attribute'])->value)->toBe(42);

    $decimal = Attribute::factory()->decimal()->create();
    $pav = ProductAttributeValue::factory()->for($product)->for($decimal, 'attribute')->decimal(1.5)->create();
    expect($pav->fresh(['attribute'])->value)->toBe(1.5);

    $boolean = Attribute::factory()->boolean()->create();
    $pav = ProductAttributeValue::factory()->for($product)->for($boolean, 'attribute')->boolean(true)->create();
    expect($pav->fresh(['attribute'])->value)->toBeTrue();

    $string = Attribute::factory()->create(['data_type' => AttributeType::String]);
    $pav = ProductAttributeValue::factory()->for($product)->for($string, 'attribute')->create(['value_string' => 'hello']);
    expect($pav->fresh(['attribute'])->value)->toBe('hello');

    $enum = Attribute::factory()->enum(2)->create();
    $option = $enum->options->first();
    $pav = ProductAttributeValue::factory()->for($product)->for($enum, 'attribute')->option($option->id)->create();
    expect($pav->fresh(['attribute', 'option'])->value)->toBe($option->value);
});
