<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Pluck the leaf keys of a top-level navigation section (management,
 * configuration, administration) from the `/api/navigation` `data` payload.
 *
 * @param  array<int, array<string, mixed>>  $data
 * @return Collection<int, string>
 */
function navigationSectionKeys(array $data, string $sectionKey): Collection
{
    $section = collect($data)->firstWhere('key', $sectionKey);

    return collect(data_get($section, 'children', []))->pluck('key');
}

/**
 * A consistent 4-level geo chain (Country -> State -> Province -> City),
 * shared across every domain that validates BR-4 geo-hierarchy consistency
 * (spec 0027): projects, campaigns, operational sites, addresses. Extracted
 * here (engineering.md §1.2) instead of duplicating the local
 * `siteGeoChain()` pattern already used by OperationalSiteCrudTest.
 *
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function geoChain(): array
{
    $country = Country::factory()->create(['name' => 'Italia']);
    $state = State::factory()->create(['name' => 'Lombardia', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milano', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milano', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}
