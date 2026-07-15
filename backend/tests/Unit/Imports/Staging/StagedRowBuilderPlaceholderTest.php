<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Imports\LeadsImportDefinition;
use App\Imports\Staging\StagedRowBuilder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// spec 0033 delta D-2026-07-15-placeholder-review-fields — StagedRowBuilder
// defaults a blank requiredForCreation() field to config('imports.placeholder')
// after recognizers ran, flagging the row for review instead of rejecting it.
// ---------------------------------------------------------------------------

function leadsPlaceholderBuilder(): StagedRowBuilder
{
    return new StagedRowBuilder(
        app(LeadsImportDefinition::class),
        User::factory()->make(['id' => 1]),
        ['Full Name' => 'full_name'],
        ImportDedupMode::CreateNew,
    );
}

it('defaults last_name to the placeholder for a single-token full_name and flags the row warning', function () {
    $outcome = leadsPlaceholderBuilder()->build(1, ['Full Name' => 'Mario']);

    expect($outcome->status)->toBe(ImportRowStatus::Warning)
        ->and($outcome->mappedValues['first_name'])->toBe('Mario')
        ->and($outcome->mappedValues['last_name'])->toBe('SCONOSCIUTO')
        ->and($outcome->messages)->toBe(['last_name was empty and defaulted to SCONOSCIUTO; review it.']);
});

it('defaults both first_name and last_name to the placeholder when full_name is blank', function () {
    $outcome = leadsPlaceholderBuilder()->build(1, ['Full Name' => '']);

    expect($outcome->status)->toBe(ImportRowStatus::Warning)
        ->and($outcome->mappedValues['first_name'])->toBe('SCONOSCIUTO')
        ->and($outcome->mappedValues['last_name'])->toBe('SCONOSCIUTO')
        ->and($outcome->messages)->toBe([
            'first_name was empty and defaulted to SCONOSCIUTO; review it.',
            'last_name was empty and defaulted to SCONOSCIUTO; review it.',
        ]);
});

it('defaults both first_name and last_name to the placeholder when full_name is absent (unmapped column)', function () {
    $outcome = leadsPlaceholderBuilder()->build(1, []);

    expect($outcome->status)->toBe(ImportRowStatus::Warning)
        ->and($outcome->mappedValues['first_name'])->toBe('SCONOSCIUTO')
        ->and($outcome->mappedValues['last_name'])->toBe('SCONOSCIUTO');
});

it('never applies the placeholder for a well-formed two-token full_name (Valid, no placeholder)', function () {
    $outcome = leadsPlaceholderBuilder()->build(1, ['Full Name' => 'Mario Rossi']);

    expect($outcome->status)->toBe(ImportRowStatus::Valid)
        ->and($outcome->mappedValues['first_name'])->toBe('Mario')
        ->and($outcome->mappedValues['last_name'])->toBe('Rossi')
        ->and($outcome->messages)->toBeNull();
});

it('leaves the LeadsImportDefinition::reviewFields() catalogue excluding full_name, including first_name/last_name', function () {
    $definition = app(LeadsImportDefinition::class);
    $reviewFieldIds = array_column($definition->reviewFields(), 'id');

    expect($reviewFieldIds)->not->toContain('full_name')
        ->and($reviewFieldIds)->toContain('first_name')
        ->and($reviewFieldIds)->toContain('last_name');
});

it('leaves LeadsImportDefinition::requiredForCreation() as first_name/last_name', function () {
    expect(app(LeadsImportDefinition::class)->requiredForCreation())->toBe(['first_name', 'last_name']);
});
