<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// auth — every geo endpoint is gated by auth:sanctum
// ---------------------------------------------------------------------------

it('countries: 401 without authentication', function () {
    $this->getJson('/api/countries')->assertUnauthorized();
});

it('states: 401 without authentication', function () {
    $this->getJson('/api/states?country_id=1')->assertUnauthorized();
});

it('provinces: 401 without authentication', function () {
    $this->getJson('/api/provinces?state_id=1')->assertUnauthorized();
});

it('cities: 401 without authentication', function () {
    $this->getJson('/api/cities?state_id=1')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// countries — GET /api/countries
// ---------------------------------------------------------------------------

it('countries: 200 returns every country ordered by name with the resource shape', function () {
    Sanctum::actingAs(User::factory()->create());

    Country::factory()->create(['name' => 'Zambia', 'iso2' => 'ZM']);
    Country::factory()->create(['name' => 'Albania', 'iso2' => 'AL']);

    $response = $this->getJson('/api/countries')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Albania')
        ->assertJsonPath('data.1.name', 'Zambia')
        ->assertJsonStructure(['data' => [['id', 'name', 'iso2']]]);

    // Only the three allowlisted keys are exposed.
    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing(['id', 'name', 'iso2']);
});

// ---------------------------------------------------------------------------
// states — GET /api/states?country_id={id}
// ---------------------------------------------------------------------------

it('states: 422 when country_id is missing', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/states')
        ->assertStatus(422)
        ->assertJsonValidationErrors('country_id');
});

it('states: 422 when country_id does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/states?country_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('country_id');
});

it('states: 200 returns only the country states, ordered by name, with the resource shape', function () {
    Sanctum::actingAs(User::factory()->create());

    $country = Country::factory()->create();
    $other = Country::factory()->create();

    State::factory()->for($country, 'country')->create(['name' => 'Veneto']);
    State::factory()->for($country, 'country')->create(['name' => 'Abruzzo']);
    State::factory()->for($other, 'country')->create(['name' => 'Bavaria']);

    $this->getJson("/api/states?country_id={$country->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Abruzzo')
        ->assertJsonPath('data.1.name', 'Veneto')
        ->assertJsonPath('data.0.country_id', $country->id)
        ->assertJsonStructure(['data' => [['id', 'name', 'country_id']]]);
});

// ---------------------------------------------------------------------------
// provinces — GET /api/provinces?state_id={id}
// ---------------------------------------------------------------------------

it('provinces: 422 when state_id is missing', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/provinces')
        ->assertStatus(422)
        ->assertJsonValidationErrors('state_id');
});

it('provinces: 422 when state_id does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/provinces?state_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('state_id');
});

it('provinces: 200 returns only the state provinces, ordered by name, with the resource shape', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    $other = State::factory()->create();

    Province::factory()->forState($state)->create(['name' => 'Naples']);
    Province::factory()->forState($state)->create(['name' => 'Caserta']);
    Province::factory()->forState($other)->create(['name' => 'Milan']);

    $response = $this->getJson("/api/provinces?state_id={$state->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Caserta')
        ->assertJsonPath('data.1.name', 'Naples')
        ->assertJsonPath('data.0.state_id', $state->id)
        ->assertJsonStructure(['data' => [['id', 'name', 'state_id']]]);

    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing(['id', 'name', 'state_id']);
});

// ---------------------------------------------------------------------------
// cities — GET /api/cities?state_id={id}|province_id={id}&search={q}
// ---------------------------------------------------------------------------

it('cities: 422 when state_id is missing', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/cities')
        ->assertStatus(422)
        ->assertJsonValidationErrors('state_id');
});

it('cities: 422 when state_id does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/cities?state_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('state_id');
});

it('cities: 200 returns only the state cities, ordered by name, with the resource shape', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    $other = State::factory()->create();

    City::factory()->forState($state)->create(['name' => 'Verona']);
    City::factory()->forState($state)->create(['name' => 'Ancona']);
    City::factory()->forState($other)->create(['name' => 'Munich']);

    $this->getJson("/api/cities?state_id={$state->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Ancona')
        ->assertJsonPath('data.1.name', 'Verona')
        ->assertJsonPath('data.0.state_id', $state->id)
        ->assertJsonStructure(['data' => [['id', 'name', 'state_id']]]);
});

it('cities: search filters by a name LIKE prefix', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    City::factory()->forState($state)->create(['name' => 'Verona']);
    City::factory()->forState($state)->create(['name' => 'Venice']);
    City::factory()->forState($state)->create(['name' => 'Milan']);

    $this->getJson("/api/cities?state_id={$state->id}&search=Ve")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Venice')
        ->assertJsonPath('data.1.name', 'Verona');
});

it('cities: caps the result set at 50', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    City::factory()->forState($state)->count(60)->create();

    $this->getJson("/api/cities?state_id={$state->id}")
        ->assertOk()
        ->assertJsonCount(50, 'data');
});

it('cities: offset pages past the first 50 for infinite scroll', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    // Names sort deterministically as City-000 .. City-059 so the page boundary
    // is predictable regardless of insertion order.
    foreach (range(0, 59) as $index) {
        City::factory()->forState($state)->create([
            'name' => sprintf('City-%03d', $index),
        ]);
    }

    $this->getJson("/api/cities?state_id={$state->id}&offset=50")
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.name', 'City-050')
        ->assertJsonPath('data.9.name', 'City-059');
});

it('cities: 422 when province_id does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/cities?province_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('province_id');
});

it('cities: filters by province_id (the finest level), winning over state_id', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    $province = Province::factory()->forState($state)->create();
    $otherProvince = Province::factory()->forState($state)->create();

    City::factory()->forProvince($province)->create(['name' => 'Grumo Nevano']);
    City::factory()->forProvince($otherProvince)->create(['name' => 'Aversa']);
    // A city of the same state but with no province must NOT appear when
    // filtering by province.
    City::factory()->forState($state)->create(['name' => 'Orphan City']);

    $this->getJson("/api/cities?province_id={$province->id}&state_id={$state->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Grumo Nevano')
        ->assertJsonPath('data.0.province_id', $province->id)
        ->assertJsonPath('data.0.state_id', $state->id)
        ->assertJsonStructure(['data' => [['id', 'name', 'state_id', 'province_id']]]);
});
