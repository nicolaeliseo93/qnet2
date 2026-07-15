<?php

use App\Enums\ImportDedupMode;
use App\Imports\BusinessFunctionsImportDefinition;
use App\Imports\CompaniesImportDefinition;
use App\Imports\ImportDefinition;
use App\Imports\OperationalSitesImportDefinition;
use App\Imports\RolesImportDefinition;
use App\Imports\UsersImportDefinition;
use App\Models\BusinessFunction;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-019 (partial: contract) — the wizard contract extension (spec 0033) must
// keep the 5 legacy definitions green via AbstractImportDefinition defaults.
// ---------------------------------------------------------------------------

$legacyDefinitions = [
    'business-functions' => BusinessFunctionsImportDefinition::class,
    'companies' => CompaniesImportDefinition::class,
    'operational-sites' => OperationalSitesImportDefinition::class,
    'roles' => RolesImportDefinition::class,
    'users' => UsersImportDefinition::class,
];

it('still implements the extended ImportDefinition contract', function (string $class) {
    $definition = app($class);

    expect($definition)->toBeInstanceOf(ImportDefinition::class);
})->with($legacyDefinitions);

it('derives fields() from columns() (same ids/required, label fallback to id)', function (string $class) {
    $definition = app($class);

    $expected = array_map(static fn (array $column): array => [
        'id' => $column['id'],
        'label' => $column['id'],
        'required' => $column['required'] ?? false,
        'group' => null,
        'type' => 'text',
    ], $definition->columns());

    expect($definition->fields())->toBe($expected);
})->with($legacyDefinitions);

it('defaults globalConfig() to an empty array', function (string $class) {
    expect(app($class)->globalConfig())->toBe([]);
})->with($legacyDefinitions);

it('defaults recognizers() to an empty array', function (string $class) {
    expect(app($class)->recognizers())->toBe([]);
})->with($legacyDefinitions);

it('defaults supportsExtraFields() to false', function (string $class) {
    expect(app($class)->supportsExtraFields())->toBeFalse();
})->with($legacyDefinitions);

it('defaults dedupModes() to [create_only]', function (string $class) {
    expect(app($class)->dedupModes())->toBe([ImportDedupMode::CreateOnly]);
})->with($legacyDefinitions);

it('defaults requiredForCreation() to an empty array (spec 0033 delta D-2026-07-15)', function (string $class) {
    expect(app($class)->requiredForCreation())->toBe([]);
})->with($legacyDefinitions);

it('defaults reviewFields() to fields() reduced to {id,label} (spec 0033 delta D-2026-07-15)', function (string $class) {
    $definition = app($class);

    $expected = array_map(static fn (array $field): array => [
        'id' => $field['id'],
        'label' => $field['label'],
    ], $definition->fields());

    expect($definition->reviewFields())->toBe($expected);
})->with($legacyDefinitions);

it('persistRow() default delegates to the legacy createRow(), ignoring the dedup strategy', function () {
    $actor = User::factory()->create();
    $importRun = ImportRun::factory()->create(['resource' => 'business-functions']);
    $row = ImportRunRow::factory()->for($importRun)->create([
        'mapped_values' => ['name' => 'Imported Function', 'type' => 'business_unit'],
    ]);

    $definition = app(BusinessFunctionsImportDefinition::class);

    $definition->persistRow($actor, $row, globalConfig: [], dedupStrategy: 'create_new');

    expect(BusinessFunction::query()->where('name', 'Imported Function')->exists())->toBeTrue();
});
