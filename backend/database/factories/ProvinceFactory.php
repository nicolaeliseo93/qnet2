<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Minimal factory for the Province reference model.
 *
 * Read-only reference data; this factory exists only for test geo fixtures (ADR
 * 0010). A consistent country/state pair is built by default; pass
 * `forState($state)` to scope a province to an existing state (keeping
 * country_id in sync).
 *
 * @extends Factory<Province>
 */
class ProvinceFactory extends Factory
{
    protected $model = Province::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $country = Country::factory();

        return [
            'country_id' => $country,
            'state_id' => State::factory()->for($country, 'country'),
            'name' => $this->faker->unique()->city(),
            'country_code' => strtoupper($this->faker->lexify('??')),
        ];
    }

    /**
     * Scope the province to an existing state, keeping country_id consistent.
     */
    public function forState(State $state): static
    {
        return $this->state(fn (array $attributes): array => [
            'state_id' => $state->id,
            'country_id' => $state->country_id,
        ]);
    }
}
