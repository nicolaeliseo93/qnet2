<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Minimal factory for the Country reference model.
 *
 * Country is read-only reference data normally populated by a deterministic
 * seed; this factory exists only so tests can build geo fixtures (ADR 0010
 * allows adding a reference-data factory when a test needs it). It produces a
 * valid country row covering every NOT NULL column.
 *
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'iso2' => strtoupper($this->faker->unique()->lexify('??')),
            'name' => $this->faker->unique()->country(),
            'status' => 1,
            'phone_code' => (string) $this->faker->numberBetween(1, 999),
            'iso3' => strtoupper($this->faker->unique()->lexify('???')),
            'region' => $this->faker->randomElement(['Europe', 'Asia', 'Americas', 'Africa', 'Oceania']),
            'subregion' => $this->faker->word(),
        ];
    }
}
