<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-007 — schema
// ---------------------------------------------------------------------------

it('creates the product_categories and attribute_category tables with the expected columns', function () {
    expect(Schema::hasTable('product_categories'))->toBeTrue();
    expect(Schema::hasColumns('product_categories', ['id', 'name', 'parent_id', 'description']))->toBeTrue();

    expect(Schema::hasTable('attribute_category'))->toBeTrue();
    expect(Schema::hasColumns('attribute_category', ['id', 'attribute_id', 'category_id', 'is_required', 'sort_order']))->toBeTrue();
});

it('a category with children cannot be deleted at the database level (restrictOnDelete)', function () {
    $parent = ProductCategory::factory()->create();
    ProductCategory::factory()->childOf($parent)->create();

    expect(fn () => DB::table('product_categories')->where('id', $parent->id)->delete())
        ->toThrow(QueryException::class);
});

it('down() reverses the product_categories migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_07_110200_create_product_categories_table.php');

    Schema::dropIfExists('products');
    Schema::dropIfExists('attribute_category');
    $migration->down();

    expect(Schema::hasTable('product_categories'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('product_categories'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-007 — model relations, activity log
// ---------------------------------------------------------------------------

it('parent()/children() are self-referencing relations', function () {
    $category = new ProductCategory;

    expect($category->parent())->toBeInstanceOf(BelongsTo::class);
    expect($category->children())->toBeInstanceOf(HasMany::class);
});

it('attributes() is a BelongsToMany relation with is_required/sort_order pivot', function () {
    $relation = (new ProductCategory)->attributes();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
    expect($relation->getPivotColumns())->toContain('is_required', 'sort_order');
});

it('products() is a HasMany relation to Product', function () {
    $relation = (new ProductCategory)->products();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Product::class);
});

it('logs model activity', function () {
    expect(class_uses(ProductCategory::class))->toHaveKey(LogsModelActivity::class);
});

it('registers the "product_category" morph alias', function () {
    expect(array_search(ProductCategory::class, Relation::morphMap(), true))->toBe('product_category');
});
