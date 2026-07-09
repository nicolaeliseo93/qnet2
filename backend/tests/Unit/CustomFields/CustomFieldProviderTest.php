<?php

declare(strict_types=1);

use App\CustomFields\CustomFieldProvider;
use App\Models\CustomFieldDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// AC-006: only active definitions, ordered by sort_order.
it('returns only active definitions for the entity_type, ordered by sort_order', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'second', 'sort_order' => 2]);
    CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'first', 'sort_order' => 1]);
    CustomFieldDefinition::factory()->forEntity('companies')->inactive()->create(['key' => 'hidden', 'sort_order' => 0]);
    CustomFieldDefinition::factory()->forEntity('products')->create(['key' => 'other-entity', 'sort_order' => 0]);

    $provider = new CustomFieldProvider;

    $definitions = $provider->definitionsFor('companies');

    expect($definitions)->toHaveCount(2)
        ->and($definitions->pluck('key')->all())->toBe(['first', 'second']);
});

// AC-006: caching — second call hits cache (no additional query).
it('caches definitionsFor across calls until forget() is called', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'cached-field']);

    $provider = new CustomFieldProvider;

    $first = $provider->definitionsFor('companies');
    expect($first->pluck('key')->all())->toBe(['cached-field']);

    // A definition created AFTER the first read must NOT appear (still cached).
    CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'added-after-cache']);

    $second = $provider->definitionsFor('companies');
    expect($second->pluck('key')->all())->toBe(['cached-field']);

    $provider->forget('companies');

    $third = $provider->definitionsFor('companies');
    expect($third->pluck('key')->sort()->values()->all())->toBe(['added-after-cache', 'cached-field']);
});

it('forget() only invalidates the given entity_type, not others', function (): void {
    CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'company-field']);
    CustomFieldDefinition::factory()->forEntity('products')->create(['key' => 'product-field']);

    $provider = new CustomFieldProvider;

    // Prime the per-request memo for both entity types.
    $provider->definitionsFor('companies');
    $provider->definitionsFor('products');

    // Rows added after priming stay hidden until the memo is forgotten.
    CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'company-added']);
    CustomFieldDefinition::factory()->forEntity('products')->create(['key' => 'product-added']);

    $provider->forget('companies');

    // companies memo cleared → new row visible; products memo intact → not yet.
    expect($provider->definitionsFor('companies')->pluck('key')->all())
        ->toContain('company-added')
        ->and($provider->definitionsFor('products')->pluck('key')->all())
        ->not->toContain('product-added');
});

// Regression (bug: __PHP_Incomplete_Class from the database cache store):
// definitionsFor must return a real Collection on every call, never a
// serialization artefact, and eager-load options (no lazy-load under
// preventLazyLoading).
it('returns a Collection with options eager-loaded on repeated calls', function (): void {
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->create(['type' => 'enum']);
    $definition->options()->create(['value' => 'a', 'label' => 'A', 'sort_order' => 0]);

    $provider = new CustomFieldProvider;

    expect($provider->definitionsFor('companies'))->toBeInstanceOf(Collection::class)
        ->and($provider->definitionsFor('companies'))->toBeInstanceOf(Collection::class)
        ->and($provider->definitionsFor('companies')->first()->relationLoaded('options'))->toBeTrue();
});

// AC-006: field keys namespaced 'custom.<key>' via the helper.
it('namespaces a raw key with the custom. prefix', function (): void {
    $provider = new CustomFieldProvider;

    expect($provider->namespacedKey('notes'))->toBe('custom.notes')
        ->and(CustomFieldProvider::KEY_PREFIX)->toBe('custom.');
});
