<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Imports\LeadsImportDefinition;
use App\Imports\Staging\StagedRowBuilder;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Support\Import\StagedRowReviser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// spec 0033 delta D-2026-07-15-placeholder-review-fields — StagedRowReviser
// re-validates from the EDITED field values merged onto mapped_values, never
// by rebuilding raw_values: a last_name hand-corrected off the placeholder
// holds through re-validation, and clearing it re-applies the placeholder.
// ---------------------------------------------------------------------------

function stagedSingleTokenLeadRow(): ImportRunRow
{
    $definition = app(LeadsImportDefinition::class);
    $actor = User::factory()->create();
    $mapping = ['Full Name' => 'full_name'];

    $outcome = (new StagedRowBuilder($definition, $actor, $mapping, ImportDedupMode::CreateNew))
        ->build(1, ['Full Name' => 'Mario']);

    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'column_mapping' => $mapping,
        'dedup_strategy' => 'create_new',
    ]);

    return ImportRunRow::factory()->for($run)->create([
        'row_number' => 1,
        'mapped_values' => $outcome->mappedValues,
        'resolved' => $outcome->resolved,
        'status' => $outcome->status,
        'messages' => $outcome->messages,
    ]);
}

it('the single-token fixture stages first_name from the token and last_name as the placeholder, row warning', function () {
    $row = stagedSingleTokenLeadRow();

    expect($row->status)->toBe(ImportRowStatus::Warning)
        ->and($row->mapped_values['first_name'])->toBe('Mario')
        ->and($row->mapped_values['last_name'])->toBe('SCONOSCIUTO');
});

it('a hand-corrected last_name (off the placeholder) holds through re-validation to Valid and persists', function () {
    $row = stagedSingleTokenLeadRow();
    $reviser = app(StagedRowReviser::class);

    $revised = $reviser->revise(app(LeadsImportDefinition::class), User::factory()->create(), $row->importRun, $row, ['last_name' => 'Rossi']);

    expect($revised->status)->toBe(ImportRowStatus::Valid)
        ->and($revised->mapped_values['last_name'])->toBe('Rossi')
        ->and($revised->mapped_values['first_name'])->toBe('Mario')
        ->and($revised->is_edited)->toBeTrue()
        ->and($revised->messages)->toBeNull();
});

it('clearing an edited last_name back to blank re-applies the placeholder and the warning', function () {
    $row = stagedSingleTokenLeadRow();
    $reviser = app(StagedRowReviser::class);
    $definition = app(LeadsImportDefinition::class);
    $actor = User::factory()->create();

    $reviser->revise($definition, $actor, $row->importRun, $row, ['last_name' => 'Rossi']);
    $revised = $reviser->revise($definition, $actor, $row->importRun->fresh(), $row->fresh(), ['last_name' => '']);

    expect($revised->status)->toBe(ImportRowStatus::Warning)
        ->and($revised->mapped_values['last_name'])->toBe('SCONOSCIUTO')
        ->and($revised->messages)->toBe(['last_name was empty and defaulted to SCONOSCIUTO; review it.']);
});

it('never re-derives last_name from full_name once first_name is already present (recognizer skip on revise)', function () {
    // Guards against the OR-based alreadyMapped() regressing back to
    // re-splitting full_name whenever only ONE of first/last is edited.
    $row = stagedSingleTokenLeadRow();
    $reviser = app(StagedRowReviser::class);

    $revised = $reviser->revise(app(LeadsImportDefinition::class), User::factory()->create(), $row->importRun, $row, ['last_name' => 'Bianchi']);

    expect($revised->mapped_values['full_name'])->toBe('Mario')
        ->and($revised->mapped_values['first_name'])->toBe('Mario')
        ->and($revised->mapped_values['last_name'])->toBe('Bianchi');
});
