<?php

use App\Imports\BusinessFunctionsImportDefinition;
use App\Imports\CompaniesImportDefinition;
use App\Imports\ImportRegistry;
use App\Imports\OperationalSitesImportDefinition;
use App\Imports\RolesImportDefinition;
use App\Imports\UsersImportDefinition;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Stubs\StubImportDefinition;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-002 — registry resolve + unknown domain
// ---------------------------------------------------------------------------

it('resolves a registered domain to its definition via the container (dependencies injected)', function () {
    config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class]]);

    $definition = app(ImportRegistry::class)->resolve('stub-widgets');

    expect($definition)->toBeInstanceOf(StubImportDefinition::class)
        ->and($definition->domain())->toBe('stub-widgets')
        ->and($definition->resource())->toBe('stub-widgets');
});

it('throws ModelNotFoundException for an unregistered domain (404 via BaseApiController)', function () {
    config(['imports.definitions' => []]);

    expect(fn () => app(ImportRegistry::class)->resolve('unknown-domain'))
        ->toThrow(ModelNotFoundException::class);
});

// Requirement changed across Lane A + the roles/users follow-up: config/
// imports.php now registers the 5 real definitions instead of shipping empty.
it('config/imports.php registers the 5 real definitions', function () {
    expect(config('imports.definitions'))->toBe([
        'business-functions' => BusinessFunctionsImportDefinition::class,
        'companies' => CompaniesImportDefinition::class,
        'operational-sites' => OperationalSitesImportDefinition::class,
        'roles' => RolesImportDefinition::class,
        'users' => UsersImportDefinition::class,
    ]);
});
