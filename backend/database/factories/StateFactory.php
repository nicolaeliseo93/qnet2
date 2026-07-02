<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Minimal factory for the State reference model.
 *
 * Read-only reference data; this factory exists only for test geo fixtures (ADR
 * 0010). The parent country is built via a related factory by default so
 * `State::factory()->create()` is self-sufficient; pass `for($country)` to scope
 * it to an existing country.
 *
 * @extends Factory<State>
 */
class StateFactory extends Factory
{
    protected $model = State::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => $this->faker->unique()->state(),
            'country_code' => strtoupper($this->faker->lexify('??')),
        ];
    }
}
