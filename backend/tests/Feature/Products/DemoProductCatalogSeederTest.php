<?php

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductCategoryService;
use Database\Seeders\DemoProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds attributes of all 5 field types, ENUM ones carrying options', function () {
    $this->seed(DemoProductCatalogSeeder::class);

    $types = Attribute::pluck('type')->unique()->values()->all();
    expect($types)->toEqualCanonicalizing(['text', 'integer', 'decimal', 'boolean', 'enum']);

    $deliveryMode = Attribute::where('code', 'delivery_mode')->firstOrFail();
    expect($deliveryMode->type)->toBe('enum');
    expect($deliveryMode->options)->toHaveCount(3);
});

it('builds a multi-level category tree, `delivery_mode` reused across both roots (never duplicated)', function () {
    $this->seed(DemoProductCatalogSeeder::class);

    expect(Attribute::where('code', 'delivery_mode')->count())->toBe(1);

    $consulting = ProductCategory::where('name', 'Consulenza')->firstOrFail();
    $training = ProductCategory::where('name', 'Formazione')->firstOrFail();
    $deliveryModeId = Attribute::where('code', 'delivery_mode')->value('id');

    expect($consulting->attributes->pluck('id'))->toContain($deliveryModeId);
    expect($training->attributes->pluck('id'))->toContain($deliveryModeId);

    $softwareDev = ProductCategory::where('name', 'Sviluppo Software')->firstOrFail();
    expect($softwareDev->parent->name)->toBe('IT');
    expect($softwareDev->parent->parent->name)->toBe('Consulenza');
});

it('a Sviluppo Software\'s effective attributes include both its own and every inherited one', function () {
    $this->seed(DemoProductCatalogSeeder::class);

    $softwareDev = ProductCategory::where('name', 'Sviluppo Software')->firstOrFail();
    $effective = app(ProductCategoryService::class)->effectiveAttributes($softwareDev);
    $codes = $effective->pluck('code')->all();

    // Inherited from Consulenza (grandparent) and IT (parent).
    expect($codes)->toContain('provider', 'sla_hours', 'delivery_mode', 'seniority_level', 'duration_hours');
    // Own assignment.
    expect($codes)->toContain('technology', 'on_call');

    $ownEntries = $effective->whereIn('code', ['technology', 'on_call']);
    expect($ownEntries->pluck('inherited')->unique()->all())->toBe([false]);

    $inheritedEntries = $effective->whereIn('code', ['provider', 'seniority_level']);
    expect($inheritedEntries->pluck('inherited')->unique()->all())->toBe([true]);
});

it('seeds a demo service directly in an intermediate category (IT), not only leaves', function () {
    $this->seed(DemoProductCatalogSeeder::class);

    $it = ProductCategory::where('name', 'IT')->firstOrFail();
    expect(Product::where('category_id', $it->id)->exists())->toBeTrue();
});

it('assigns the root categories their own business function, children inherit it read-side', function () {
    // The seeder resolves the business function BY NAME, so it must already
    // exist (DemoBusinessFunctionSeeder runs earlier in DemoDataSeeder).
    $it = BusinessFunction::create(['name' => 'Sistemi Informativi (IT)', 'is_business_service' => true]);
    BusinessFunction::create(['name' => 'Risorse Umane', 'is_business_unit' => true]);

    $this->seed(DemoProductCatalogSeeder::class);

    $consulting = ProductCategory::where('name', 'Consulenza')->firstOrFail();
    expect($consulting->business_function_id)->toBe($it->id);

    // A descendant carries none of its own but inherits transitively.
    $softwareDev = ProductCategory::where('name', 'Sviluppo Software')->firstOrFail();
    expect($softwareDev->business_function_id)->toBeNull();

    $effective = app(CategoryHierarchy::class)->effectiveBusinessFunction($softwareDev);
    expect($effective['id'])->toBe($it->id);
    expect($effective['inherited'])->toBeTrue();
});

it('leaves the business function null when the named function is not seeded', function () {
    $this->seed(DemoProductCatalogSeeder::class);

    expect(ProductCategory::whereNotNull('business_function_id')->count())->toBe(0);
});

it('is idempotent — re-running duplicates nothing', function () {
    $this->seed(DemoProductCatalogSeeder::class);

    $attributeCount = Attribute::count();
    $optionCount = AttributeOption::count();
    $categoryCount = ProductCategory::count();
    $pivotCount = DB::table('attribute_category')->count();
    $productCount = Product::count();

    $this->seed(DemoProductCatalogSeeder::class);

    expect(Attribute::count())->toBe($attributeCount);
    expect(AttributeOption::count())->toBe($optionCount);
    expect(ProductCategory::count())->toBe($categoryCount);
    expect(DB::table('attribute_category')->count())->toBe($pivotCount);
    expect(Product::count())->toBe($productCount);
});
