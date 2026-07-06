<?php

use App\Imports\Support\GeoResolver;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * A real geo chain in the reference dataset's ENGLISH spelling (country ->
 * state/region -> province -> city), so the tests exercise the Italian->English
 * localization the resolver now applies (mirrors the real world.sql data).
 *
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function geoResolverChain(): array
{
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->create(['name' => 'Lombardy', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milan', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milan', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

// ---------------------------------------------------------------------------
// AC-005 — GeoResolver: resolve ok, not-found, ambiguous
// ---------------------------------------------------------------------------

it('resolves the full hierarchy, translating Italian names + province code case-insensitively', function () {
    $geo = geoResolverChain();

    // Italian country/region, the province PLATE CODE, and an anglicized city.
    $result = app(GeoResolver::class)->resolve('itALIA', 'lombardia', 'MI', 'Milano');

    expect($result->isResolved())->toBeTrue()
        ->and($result->countryId)->toBe($geo['country']->id)
        ->and($result->stateId)->toBe($geo['state']->id)
        ->and($result->provinceId)->toBe($geo['province']->id)
        ->and($result->cityId)->toBe($geo['city']->id);
});

it('resolves a label-noisy comune by stripping the site label before matching', function () {
    $geo = geoResolverChain();
    $city = City::factory()->create([
        'name' => 'Frattamaggiore',
        'province_id' => $geo['province']->id,
        'state_id' => $geo['state']->id,
        'country_id' => $geo['country']->id,
    ]);

    $result = app(GeoResolver::class)->resolve('Italia', 'Lombardia', 'MI', 'FRATTAMAGGIORE 1 (HQ)');

    expect($result->isResolved())->toBeTrue()
        ->and($result->cityId)->toBe($city->id);
});

it('backfills a blank region/country from the province plate code', function () {
    $geo = geoResolverChain();

    // No country, no region — only the plate code + comune (the companies shape).
    $result = app(GeoResolver::class)->resolve(null, null, 'MI', 'Milano');

    expect($result->isResolved())->toBeTrue()
        ->and($result->provinceId)->toBe($geo['province']->id)
        ->and($result->stateId)->toBe($geo['state']->id)
        ->and($result->countryId)->toBe($geo['country']->id)
        ->and($result->cityId)->toBe($geo['city']->id);
});

it('skips blank/absent levels with no error and no id', function () {
    $result = app(GeoResolver::class)->resolve(null, null, null, null);

    expect($result->isResolved())->toBeTrue()
        ->and($result->countryId)->toBeNull()
        ->and($result->stateId)->toBeNull()
        ->and($result->provinceId)->toBeNull()
        ->and($result->cityId)->toBeNull();
});

it('fails with a reason when a name does not exist', function () {
    geoResolverChain();

    $result = app(GeoResolver::class)->resolve('Nonexistentland', null, null, null);

    expect($result->isResolved())->toBeFalse()
        ->and($result->error)->toContain('Nonexistentland');
});

it('disambiguates a city WITHIN the given province: a homonym city in another province fails', function () {
    $geo = geoResolverChain();

    // Homonym city "Milan" in a DIFFERENT province ("Bergamo").
    $otherProvince = Province::factory()->create(['name' => 'Bergamo', 'state_id' => $geo['state']->id, 'country_id' => $geo['country']->id]);
    City::factory()->create(['name' => 'Milan', 'province_id' => $otherProvince->id, 'state_id' => $geo['state']->id, 'country_id' => $geo['country']->id]);

    // Scoped to the ORIGINAL province (via its plate code) still succeeds unambiguously.
    $scoped = app(GeoResolver::class)->resolve(null, null, 'MI', 'Milano');
    expect($scoped->isResolved())->toBeTrue()
        ->and($scoped->cityId)->toBe($geo['city']->id);

    // With NO province scope it is now ambiguous (two provinces have a "Milan").
    $unscoped = app(GeoResolver::class)->resolve(null, null, null, 'Milano');
    expect($unscoped->isResolved())->toBeFalse();
});

it('fails when a province is ambiguous within the given state', function () {
    $geo = geoResolverChain();
    Province::factory()->create(['name' => 'Milan', 'state_id' => $geo['state']->id, 'country_id' => $geo['country']->id]);

    $result = app(GeoResolver::class)->resolve(null, 'Lombardia', 'MI', null);

    expect($result->isResolved())->toBeFalse()
        ->and($result->error)->toContain('MI');
});
