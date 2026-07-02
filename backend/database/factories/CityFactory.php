<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Minimal factory for the City reference model.
 *
 * Read-only reference data; this factory exists only for test geo fixtures (ADR
 * 0010). A consistent country/state pair is built by default (no province, as
 * many countries have none); pass `forState($state)` to scope a city to an
 * existing state, or `forProvince($province)` to also attach a province (keeping
 * country_id / state_id in sync).
 *
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $country = Country::factory();

        return [
            'country_id' => $country,
            'state_id' => State::factory()->for($country, 'country'),
            'province_id' => null,
            'name' => $this->faker->unique()->city(),
            'country_code' => strtoupper($this->faker->lexify('??')),
        ];
    }

    /**
     * Scope the city to an existing state, keeping country_id consistent.
     */
    public function forState(State $state): static
    {
        return $this->state(fn (array $attributes): array => [
            'state_id' => $state->id,
            'country_id' => $state->country_id,
        ]);
    }

    /**
     * Scope the city to an existing province, keeping state_id / country_id
     * consistent with the province's ancestors.
     */
    public function forProvince(Province $province): static
    {
        return $this->state(fn (array $attributes): array => [
            'province_id' => $province->id,
            'state_id' => $province->state_id,
            'country_id' => $province->country_id,
        ]);
    }
}
