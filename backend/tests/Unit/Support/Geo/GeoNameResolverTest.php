<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Support\Geo\GeoNameResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('maps a resolved id set to its canonical reference names', function () {
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->create(['name' => 'Lazio', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Rome', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Rome', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    $names = app(GeoNameResolver::class)->names($country->id, $state->id, $province->id, $city->id);

    expect($names)->toBe([
        'country' => 'Italy',
        'region' => 'Lazio',
        'province' => 'Rome',
        'city' => 'Rome',
    ]);
});

it('yields a null name for every null id', function () {
    $names = app(GeoNameResolver::class)->names(null, null, null, null);

    expect($names)->toBe([
        'country' => null,
        'region' => null,
        'province' => null,
        'city' => null,
    ]);
});
