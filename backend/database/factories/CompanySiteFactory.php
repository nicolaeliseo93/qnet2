<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\CompanySite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySite>
 */
class CompanySiteFactory extends Factory
{
    protected $model = CompanySite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'email' => fake()->unique()->companyEmail(),
            'fiscal_code' => fake()->optional()->numerify('FISC###########'),
            'vat_number' => fake()->optional()->numerify('IT###########'),
            'phone' => fake()->optional()->phoneNumber(),
            'pec' => fake()->optional()->safeEmail(),
            'fax' => fake()->optional()->phoneNumber(),
            'notes' => fake()->optional()->sentence(),
            'is_default' => false,
        ];
    }

    /**
     * Attach a primary address (reusing AddressFactory) to the site.
     */
    public function withAddress(): static
    {
        return $this->afterCreating(function (CompanySite $companySite): void {
            Address::factory()->primary()->for($companySite, 'addressable')->create();
        });
    }

    /**
     * The exclusive default site (see CompanySiteService::setDefault).
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}
