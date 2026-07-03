<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'denomination' => fake()->unique()->company(),
            'vat_number' => fake()->optional()->numerify('IT###########'),
        ];
    }

    /**
     * Attach a primary address (reusing AddressFactory) to the company.
     */
    public function withAddress(): static
    {
        return $this->afterCreating(function (Company $company): void {
            Address::factory()->primary()->for($company, 'addressable')->create();
        });
    }
}
