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
 * A real geo chain (country -> state/region -> province -> city), mirroring
 * CompanyCrudTest::companyGeoChain().
 *
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function geoResolverChain(): array
{
    $country = Country::factory()->create(['name' => 'Italia']);
    $state = State::factory()->create(['name' => 'Lombardia', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milano', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milano', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

// ---------------------------------------------------------------------------
// AC-005 — GeoResolver: resolve ok, not-found, ambiguous
// ---------------------------------------------------------------------------

it('resolves the full hierarchy case-insensitively to the correct ids', function () {
    $geo = geoResolverChain();

    $result = (new GeoResolver)->resolve('itALIA', 'lombardia', 'MILANO', 'milano');

    expect($result->isResolved())->toBeTrue()
        ->and($result->countryId)->toBe($geo['country']->id)
        ->and($result->stateId)->toBe($geo['state']->id)
        ->and($result->provinceId)->toBe($geo['province']->id)
        ->and($result->cityId)->toBe($geo['city']->id);
});

it('skips blank/absent levels with no error and no id', function () {
    $result = (new GeoResolver)->resolve(null, null, null, null);

    expect($result->isResolved())->toBeTrue()
        ->and($result->countryId)->toBeNull()
        ->and($result->stateId)->toBeNull()
        ->and($result->provinceId)->toBeNull()
        ->and($result->cityId)->toBeNull();
});

it('fails with a reason when a name does not exist', function () {
    geoResolverChain();

    $result = (new GeoResolver)->resolve('Nonexistentland', null, null, null);

    expect($result->isResolved())->toBeFalse()
        ->and($result->error)->toContain('Nonexistentland');
});

it('disambiguates a city WITHIN the given province: a homonym city in another province fails', function () {
    $geo = geoResolverChain();

    // Homonym city "Milano" in a DIFFERENT province ("Bergamo").
    $otherProvince = Province::factory()->create(['name' => 'Bergamo', 'state_id' => $geo['state']->id, 'country_id' => $geo['country']->id]);
    City::factory()->create(['name' => 'Milano', 'province_id' => $otherProvince->id, 'state_id' => $geo['state']->id, 'country_id' => $geo['country']->id]);

    // Resolving "Milano" city scoped to the ORIGINAL province still succeeds unambiguously.
    $scoped = (new GeoResolver)->resolve(null, null, 'Milano', 'Milano');
    expect($scoped->isResolved())->toBeTrue()
        ->and($scoped->cityId)->toBe($geo['city']->id);

    // Resolving "Milano" city with NO province scope is now ambiguous (two provinces have it).
    $unscoped = (new GeoResolver)->resolve(null, null, null, 'Milano');
    expect($unscoped->isResolved())->toBeFalse();
});

it('fails when a province name is ambiguous within the given state', function () {
    $geo = geoResolverChain();
    Province::factory()->create(['name' => 'Milano', 'state_id' => $geo['state']->id, 'country_id' => $geo['country']->id]);

    $result = (new GeoResolver)->resolve(null, 'Lombardia', 'Milano', null);

    expect($result->isResolved())->toBeFalse()
        ->and($result->error)->toContain('Milano');
});
