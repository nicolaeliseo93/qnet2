<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly, mirroring OpportunityTest.

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// model relations (AC-098)
// ---------------------------------------------------------------------------

it('every relation is a BelongsTo to the expected model', function () {
    $line = new OpportunityProductLine;

    expect($line->opportunity())->toBeInstanceOf(BelongsTo::class)
        ->and($line->opportunity()->getRelated())->toBeInstanceOf(Opportunity::class)
        ->and($line->businessFunction())->toBeInstanceOf(BelongsTo::class)
        ->and($line->businessFunction()->getRelated())->toBeInstanceOf(BusinessFunction::class)
        ->and($line->productCategory())->toBeInstanceOf(BelongsTo::class)
        ->and($line->productCategory()->getRelated())->toBeInstanceOf(ProductCategory::class);
});

it('persists opportunity_id/business_function_id/product_category_id via mass assignment', function () {
    $opportunity = Opportunity::factory()->create();
    $businessFunction = BusinessFunction::factory()->create();
    $productCategory = ProductCategory::factory()->create();

    $line = OpportunityProductLine::create([
        'opportunity_id' => $opportunity->id,
        'business_function_id' => $businessFunction->id,
        'product_category_id' => $productCategory->id,
    ]);

    expect($line->exists)->toBeTrue()
        ->and($line->opportunity->is($opportunity))->toBeTrue()
        ->and($line->businessFunction->is($businessFunction))->toBeTrue()
        ->and($line->productCategory->is($productCategory))->toBeTrue();
});
