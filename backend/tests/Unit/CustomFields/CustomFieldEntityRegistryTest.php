<?php

declare(strict_types=1);

use App\Authorization\AuthorizationRegistry;
use App\CustomFields\CustomFieldEntityRegistry;
use App\Models\Address;
use App\Models\Company;
use App\Tables\CompaniesTableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Needs the app container to resolve TableRegistry/AuthorizationRegistry
// definitions (some depend on FieldPermissionRepository, which touches the
// DB) — mirrors tests/Unit/Authorization's binding choice.
uses(TestCase::class, RefreshDatabase::class);

// AC-005: entities() lists only domains present in BOTH registries.
it('lists only domains registered in both TableRegistry and AuthorizationRegistry', function (): void {
    $registry = app(CustomFieldEntityRegistry::class);

    $entityTypes = array_column($registry->entities(), 'entity_type');

    expect($entityTypes)->toContain('companies')
        ->and($entityTypes)->toContain('products')
        ->and($entityTypes)->not->toContain('widgets');
});

it('excludes a domain that has a table but no authorization registered', function (): void {
    config(['tables.definitions' => array_merge(
        config('tables.definitions'),
        ['ghost-domain' => CompaniesTableDefinition::class],
    )]);

    $registry = new CustomFieldEntityRegistry(app(TableRegistry::class), app(AuthorizationRegistry::class));

    expect($registry->isCustomFieldable('ghost-domain'))->toBeFalse();
});

it('exposes an i18n-style label per entity_type', function (): void {
    $registry = app(CustomFieldEntityRegistry::class);

    $companies = collect($registry->entities())->firstWhere('entity_type', 'companies');

    expect($companies)->not->toBeNull()
        ->and($companies['label'])->toBe('customFields.entities.companies');
});

it('resolves isCustomFieldable/modelClassFor/resourceFor for a registered domain', function (): void {
    $registry = app(CustomFieldEntityRegistry::class);

    expect($registry->isCustomFieldable('companies'))->toBeTrue()
        ->and($registry->isCustomFieldable('does-not-exist'))->toBeFalse()
        ->and($registry->modelClassFor('companies'))->toBe(Company::class)
        ->and($registry->modelClassFor('does-not-exist'))->toBeNull()
        ->and($registry->resourceFor('companies'))->toBe('companies');
});

// AC-005: entityTypeForModel(Company) => 'companies'.
it('reverse-resolves entity_type for a real model instance', function (): void {
    $registry = app(CustomFieldEntityRegistry::class);

    $company = Company::factory()->make();

    expect($registry->entityTypeForModel($company))->toBe('companies');

    $address = new Address;

    expect($registry->entityTypeForModel($address))->toBeNull();
});

it('memoizes the built map across repeated calls (build once)', function (): void {
    $registry = app(CustomFieldEntityRegistry::class);

    $first = $registry->entities();
    $second = $registry->entities();

    expect($second)->toBe($first);
});
