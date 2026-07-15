<?php

use App\Imports\ImportRowContext;
use App\Imports\Recognition\NameSplitRecognizer;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

// ---------------------------------------------------------------------------
// AC-004 — NameSplitRecognizer: full_name -> first_name/last_name
// ---------------------------------------------------------------------------

function nameSplitContext(): ImportRowContext
{
    return new ImportRowContext(1, User::factory()->make());
}

it('splits a simple "First Last" name', function () {
    $result = (new NameSplitRecognizer)->recognize(nameSplitContext(), ['full_name' => 'Mario Rossi']);

    expect($result->needsReview)->toBeFalse()
        ->and($result->resolved)->toBe(['first_name' => 'Mario', 'last_name' => 'Rossi']);
});

it('splits a "Last First"-ordered two-word name the same deterministic way', function () {
    // Telling first-name-first from surname-first apart needs a name
    // dictionary (out of scope, no hard-coded lists) — both two-word inputs
    // split token0/token1 identically and coherently.
    $result = (new NameSplitRecognizer)->recognize(nameSplitContext(), ['full_name' => 'Rossi Mario']);

    expect($result->needsReview)->toBeFalse()
        ->and($result->resolved)->toBe(['first_name' => 'Rossi', 'last_name' => 'Mario']);
});

it('splits a compound (multi-word) first name, keeping the trailing token as the surname', function () {
    $result = (new NameSplitRecognizer)->recognize(nameSplitContext(), ['full_name' => 'Maria Teresa Rossi']);

    expect($result->needsReview)->toBeFalse()
        ->and($result->resolved)->toBe(['first_name' => 'Maria Teresa', 'last_name' => 'Rossi']);
});

it('splits a compound surname started by the "de" particle', function () {
    $result = (new NameSplitRecognizer)->recognize(nameSplitContext(), ['full_name' => 'Anna De Santis']);

    expect($result->needsReview)->toBeFalse()
        ->and($result->resolved)->toBe(['first_name' => 'Anna', 'last_name' => 'De Santis']);
});

it('splits a double surname started by the "dal" particle', function () {
    $result = (new NameSplitRecognizer)->recognize(nameSplitContext(), ['full_name' => 'Luca Dal Bianco']);

    expect($result->needsReview)->toBeFalse()
        ->and($result->resolved)->toBe(['first_name' => 'Luca', 'last_name' => 'Dal Bianco']);
});

it('splits a double surname started by the "lo" particle', function () {
    $result = (new NameSplitRecognizer)->recognize(nameSplitContext(), ['full_name' => 'Giulia Lo Russo']);

    expect($result->needsReview)->toBeFalse()
        ->and($result->resolved)->toBe(['first_name' => 'Giulia', 'last_name' => 'Lo Russo']);
});

it('flags a single-word name as needing review with a best-effort split', function () {
    $result = (new NameSplitRecognizer)->recognize(nameSplitContext(), ['full_name' => 'Rossi']);

    expect($result->needsReview)->toBeTrue()
        ->and($result->resolved)->toBe(['first_name' => null, 'last_name' => 'Rossi'])
        ->and($result->messages)->not->toBeEmpty();
});

it('never overwrites already-mapped first_name/last_name', function () {
    $result = (new NameSplitRecognizer)->recognize(nameSplitContext(), [
        'full_name' => 'Mario Rossi',
        'first_name' => 'Giovanni',
        'last_name' => 'Bianchi',
    ]);

    expect($result->resolved)->toBe([])
        ->and($result->needsReview)->toBeFalse();
});

it('is a no-op when full_name is absent', function () {
    $result = (new NameSplitRecognizer)->recognize(nameSplitContext(), ['email' => 'a@b.com']);

    expect($result->resolved)->toBe([])
        ->and($result->needsReview)->toBeFalse()
        ->and($result->messages)->toBe([]);
});
