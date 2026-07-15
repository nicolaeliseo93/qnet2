<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Imports\Staging\StagedRowBuilder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Jobs\Fixtures\FakeWizardImportDefinition;

uses(TestCase::class, RefreshDatabase::class);

function stagedRowBuilder(array $columnMapping, ImportDedupMode $dedupMode = ImportDedupMode::CreateNew): StagedRowBuilder
{
    return new StagedRowBuilder(
        app(FakeWizardImportDefinition::class),
        User::factory()->make(['id' => 1]),
        $columnMapping,
        $dedupMode,
    );
}

// ---------------------------------------------------------------------------
// AC-008 machinery — StagedRowBuilder: mapping + recognizers + validation + dedup
// ---------------------------------------------------------------------------

it('maps raw columns to field ids and drops __ignore__ columns', function () {
    $builder = stagedRowBuilder(['Full Name' => 'full_name', 'Email' => 'email', 'Note' => StagedRowBuilder::IGNORE_TARGET]);

    $outcome = $builder->build(1, ['Full Name' => 'Mario Rossi', 'Email' => 'mario@test.com', 'Note' => 'internal']);

    expect($outcome->status)->toBe(ImportRowStatus::Valid)
        ->and($outcome->mappedValues['full_name'])->toBe('Mario Rossi')
        ->and($outcome->mappedValues['email'])->toBe('mario@test.com')
        ->and($outcome->mappedValues)->not->toHaveKey('Note')
        ->and($outcome->extraValues)->toBeNull();
});

it('routes __extra__ columns into extraValues keyed by the original column name', function () {
    $builder = stagedRowBuilder(['Full Name' => 'full_name', 'Email' => 'email', 'Custom' => StagedRowBuilder::EXTRA_TARGET]);

    $outcome = $builder->build(1, ['Full Name' => 'Mario Rossi', 'Email' => 'mario@test.com', 'Custom' => 'VIP']);

    expect($outcome->extraValues)->toBe(['Custom' => 'VIP']);
});

it('merges recognizer resolved values into BOTH mappedValues and resolved', function () {
    $builder = stagedRowBuilder(['Full Name' => 'full_name', 'Email' => 'email']);

    $outcome = $builder->build(1, ['Full Name' => 'Mario Rossi', 'Email' => 'mario@test.com']);

    expect($outcome->resolved)->toBe(['domain_hint' => 'test.com'])
        ->and($outcome->mappedValues['domain_hint'])->toBe('test.com');
});

it('a low-confidence recognizer result downgrades the row to warning with its message', function () {
    $builder = stagedRowBuilder(['Full Name' => 'full_name', 'Email' => 'email']);

    $outcome = $builder->build(1, ['Full Name' => 'Mario Rossi', 'Email' => 'lowconf@test.com']);

    expect($outcome->status)->toBe(ImportRowStatus::Warning)
        ->and($outcome->messages)->toBe(['Low-confidence email domain "test.com".']);
});

it('a validateRow() failure rejects the row as error regardless of duplicate/recognizer outcome', function () {
    $builder = stagedRowBuilder(['Full Name' => 'full_name', 'Email' => 'email']);

    $outcome = $builder->build(1, ['Full Name' => '', 'Email' => 'not-an-email']);

    expect($outcome->status)->toBe(ImportRowStatus::Error)
        ->and($outcome->messages)->toBe(['full_name is required.', 'email must be a valid address.'])
        ->and($outcome->duplicateOfId)->toBeNull();
});

it('resolves a duplicate to skipped/duplicate/valid depending on the dedup strategy', function () {
    $row = ['Full Name' => 'Mario Rossi', 'Email' => FakeWizardImportDefinition::DUPLICATE_EMAIL];
    $mapping = ['Full Name' => 'full_name', 'Email' => 'email'];

    $ignored = stagedRowBuilder($mapping, ImportDedupMode::Ignore)->build(1, $row);
    $manual = stagedRowBuilder($mapping, ImportDedupMode::Manual)->build(1, $row);
    $createNew = stagedRowBuilder($mapping, ImportDedupMode::CreateNew)->build(1, $row);
    $updateExisting = stagedRowBuilder($mapping, ImportDedupMode::UpdateExisting)->build(1, $row);

    expect($ignored->status)->toBe(ImportRowStatus::Skipped)
        ->and($ignored->duplicateOfId)->toBe(FakeWizardImportDefinition::DUPLICATE_ID)
        ->and($manual->status)->toBe(ImportRowStatus::Duplicate)
        ->and($createNew->status)->toBe(ImportRowStatus::Valid)
        ->and($updateExisting->status)->toBe(ImportRowStatus::Valid)
        ->and($updateExisting->duplicateOfId)->toBe(FakeWizardImportDefinition::DUPLICATE_ID);
});
