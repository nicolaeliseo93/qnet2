<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for Address records (GAP fill: the reusable Address model had no
 * factory). Geo references are left null by default so the factory does not
 * depend on the reference tables being seeded; use withLabel() for a named
 * address, or complete() for a full address tied to a real seeded city.
 *
 *     Address::factory()->create();
 *     Address::factory()->withLabel('Billing')->create();
 *     Address::factory()->complete()->create(); // full geo, not just the street
 *     Address::factory()->for($card, 'addressable')->create(); // owned
 *
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => $this->faker->randomElement(['Home', 'Billing', 'Warehouse', null]),
            'line1' => $this->faker->streetAddress(),
            'line2' => $this->faker->optional()->secondaryAddress(),
            'postal_code' => $this->faker->postcode(),
            'city_id' => null,
            'province_id' => null,
            'state_id' => null,
            'country_id' => null,
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'is_primary' => false,
        ];
    }

    /**
     * An address with an explicit human label.
     */
    public function withLabel(string $label): static
    {
        return $this->state(fn (array $attributes): array => [
            'label' => $label,
        ]);
    }

    /**
     * The owner's primary address.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_primary' => true,
        ]);
    }

    /**
     * A complete address: not just the street, but a real city pulled from the
     * seeded geo reference tables, with its full country / state / province
     * ancestry copied from that city (which already carries those ids
     * denormalized). Falls back to the null geo defaults when the reference
     * tables have not been seeded, so the factory still works without them.
     */
    public function complete(): static
    {
        return $this->state(function (array $attributes): array {
            $city = City::query()->inRandomOrder()->first();

            if ($city === null) {
                return [];
            }

            return $this->cityAttributes($city);
        });
    }

    /**
     * Scope the address to a specific real city and copy its full geo ancestry.
     */
    public function forCity(City $city): static
    {
        return $this->state(fn (array $attributes): array => $this->cityAttributes($city));
    }

    /**
     * @return array<string, int|null>
     */
    private function cityAttributes(City $city): array
    {
        return [
            'city_id' => $city->id,
            'province_id' => $city->province_id,
            'state_id' => $city->state_id,
            'country_id' => $city->country_id,
        ];
    }
}
