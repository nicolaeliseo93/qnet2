<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('complete: fills the full geo ancestry from a real seeded city', function () {
    $country = Country::factory()->create();
    $state = State::factory()->for($country, 'country')->create();
    $province = Province::factory()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
    ]);
    $city = City::factory()->forProvince($province)->create();

    $address = Address::factory()->complete()->create();

    // The only seeded city is the one above, so complete() must resolve to it
    // and copy its denormalized country / state / province ids verbatim.
    expect($address->city_id)->toBe($city->id)
        ->and($address->province_id)->toBe($province->id)
        ->and($address->state_id)->toBe($state->id)
        ->and($address->country_id)->toBe($country->id)
        ->and($address->line1)->not->toBeNull();
});

it('complete: falls back to null geo when no reference data is seeded', function () {
    $address = Address::factory()->complete()->create();

    expect($address->city_id)->toBeNull()
        ->and($address->province_id)->toBeNull()
        ->and($address->state_id)->toBeNull()
        ->and($address->country_id)->toBeNull();
});
