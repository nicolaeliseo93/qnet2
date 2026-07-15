<?php

use App\Imports\ImportRowContext;
use App\Imports\Recognition\GeoRecognizer;
use App\Imports\Support\GeoResolver;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function geoRecognizerChain(): array
{
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->create(['name' => 'Lombardy', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milan', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milan', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

function geoRecognizerContext(): ImportRowContext
{
    return new ImportRowContext(1, User::factory()->make());
}

// ---------------------------------------------------------------------------
// AC-005 — GeoRecognizer: single-assign vs warning
// ---------------------------------------------------------------------------

it('assigns the *_id fields on a single unambiguous fuzzy match', function () {
    $geo = geoRecognizerChain();

    $result = app(GeoRecognizer::class)->recognize(geoRecognizerContext(), [
        'country' => 'Italy',
        'region' => 'Lombardy',
        'province' => 'Milano', // near-miss of "Milan", single match
        'city' => 'Milan',
    ]);

    expect($result->needsReview)->toBeFalse()
        ->and($result->resolved)->toBe([
            'country_id' => $geo['country']->id,
            'state_id' => $geo['state']->id,
            'province_id' => $geo['province']->id,
            'city_id' => $geo['city']->id,
        ])
        ->and($result->messages)->toBe([]);
});

it('flags the row for review with a candidate-carrying message when the city is ambiguous', function () {
    $geo = geoRecognizerChain();
    City::factory()->create(['name' => 'Milano', 'province_id' => $geo['province']->id, 'state_id' => $geo['state']->id, 'country_id' => $geo['country']->id]);

    // "Milann" fuzzy-matches BOTH "Milan" and "Milano" above threshold.
    $result = app(GeoRecognizer::class)->recognize(geoRecognizerContext(), [
        'country' => 'Italy',
        'region' => 'Lombardy',
        'province' => 'Milan',
        'city' => 'Milann',
    ]);

    expect($result->needsReview)->toBeTrue()
        ->and($result->resolved['city_id'])->toBeNull()
        ->and($result->resolved['country_id'])->toBe($geo['country']->id)
        ->and($result->messages)->toHaveCount(1)
        ->and($result->messages[0])->toContain('Milann');
});

it('flags the row for review when nothing is close enough to match', function () {
    geoRecognizerChain();

    $result = app(GeoRecognizer::class)->recognize(geoRecognizerContext(), [
        'country' => 'Nowhereland',
    ]);

    expect($result->needsReview)->toBeTrue()
        ->and($result->resolved)->toBe([
            'country_id' => null,
            'state_id' => null,
            'province_id' => null,
            'city_id' => null,
        ]);
});

it('is a no-op when no geo field is mapped', function () {
    geoRecognizerChain();

    $result = app(GeoRecognizer::class)->recognize(geoRecognizerContext(), ['email' => 'a@b.com']);

    expect($result->resolved)->toBe([])
        ->and($result->needsReview)->toBeFalse()
        ->and($result->messages)->toBe([]);
});

it('resolves through the app container with GeoResolver injected', function () {
    expect(app(GeoRecognizer::class))->toBeInstanceOf(GeoRecognizer::class);
    expect(app(GeoResolver::class))->toBeInstanceOf(GeoResolver::class);
});
