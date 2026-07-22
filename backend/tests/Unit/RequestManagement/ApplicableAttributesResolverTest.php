<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\RequestManagement\ApplicableAttributesResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Request Management module (spec 0049, D-4/AC-022): applicable_attributes =
// UNION (dedup per `code`) of the EFFECTIVE attributes of every product
// category across an opportunity's product lines.

uses(TestCase::class, RefreshDatabase::class);

it('returns an empty set for an opportunity with no product lines', function (): void {
    $opportunity = Opportunity::factory()->create();

    $resolved = app(ApplicableAttributesResolver::class)->resolve($opportunity);

    expect($resolved)->toBeEmpty();
});

it('returns an empty set for an opportunity whose categories carry no attributes', function (): void {
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create();

    $resolved = app(ApplicableAttributesResolver::class)->resolve($opportunity);

    expect($resolved)->toBeEmpty();
});

it('unions and dedups attributes by code across the categories of several product lines', function (): void {
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();

    $sharedAttribute = Attribute::factory()->create(['code' => 'shared_field']);
    $onlyA = Attribute::factory()->create(['code' => 'only_a']);
    $onlyB = Attribute::factory()->create(['code' => 'only_b']);

    $categoryA->attributes()->attach($sharedAttribute->id, ['is_required' => false, 'sort_order' => 1]);
    $categoryA->attributes()->attach($onlyA->id, ['is_required' => false, 'sort_order' => 0]);
    $categoryB->attributes()->attach($sharedAttribute->id, ['is_required' => false, 'sort_order' => 0]);
    $categoryB->attributes()->attach($onlyB->id, ['is_required' => false, 'sort_order' => 2]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryA->id]);
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryB->id]);

    $resolved = app(ApplicableAttributesResolver::class)->resolve($opportunity);

    expect($resolved)->toHaveCount(3)
        ->and($resolved->pluck('code')->all())->toEqualCanonicalizing(['shared_field', 'only_a', 'only_b']);
});

it('propagates is_required when the same code is required by at least one category', function (): void {
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();

    $sharedAttribute = Attribute::factory()->create(['code' => 'shared_required']);

    $categoryA->attributes()->attach($sharedAttribute->id, ['is_required' => false, 'sort_order' => 0]);
    $categoryB->attributes()->attach($sharedAttribute->id, ['is_required' => true, 'sort_order' => 0]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryA->id]);
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryB->id]);

    $resolved = app(ApplicableAttributesResolver::class)->resolve($opportunity);

    expect($resolved)->toHaveCount(1)
        ->and($resolved->first()->isRequired)->toBeTrue();
});

it('keeps a code non-required when no category requires it', function (): void {
    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();

    $sharedAttribute = Attribute::factory()->create(['code' => 'shared_optional']);

    $categoryA->attributes()->attach($sharedAttribute->id, ['is_required' => false, 'sort_order' => 0]);
    $categoryB->attributes()->attach($sharedAttribute->id, ['is_required' => false, 'sort_order' => 0]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryA->id]);
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $categoryB->id]);

    $resolved = app(ApplicableAttributesResolver::class)->resolve($opportunity);

    expect($resolved->first()->isRequired)->toBeFalse();
});

it('orders the merged set by sort_order then code', function (): void {
    $category = ProductCategory::factory()->create();

    $b = Attribute::factory()->create(['code' => 'b_field']);
    $a = Attribute::factory()->create(['code' => 'a_field']);
    $c = Attribute::factory()->create(['code' => 'c_field']);

    $category->attributes()->attach($b->id, ['is_required' => false, 'sort_order' => 1]);
    $category->attributes()->attach($a->id, ['is_required' => false, 'sort_order' => 1]);
    $category->attributes()->attach($c->id, ['is_required' => false, 'sort_order' => 0]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);

    $resolved = app(ApplicableAttributesResolver::class)->resolve($opportunity);

    expect($resolved->pluck('code')->all())->toBe(['c_field', 'a_field', 'b_field']);
});

it('does not resolve the same shared category twice across two product lines (N+1-free)', function (): void {
    $category = ProductCategory::factory()->create();
    $attribute = Attribute::factory()->create(['code' => 'once_only']);
    $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0]);

    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);

    $resolved = app(ApplicableAttributesResolver::class)->resolve($opportunity);

    expect($resolved)->toHaveCount(1)
        ->and($resolved->first()->code)->toBe('once_only');
});
