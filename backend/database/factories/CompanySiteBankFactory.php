<?php

namespace Database\Factories;

use App\Models\CompanySiteBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySiteBank>
 */
class CompanySiteBankFactory extends Factory
{
    protected $model = CompanySiteBank::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Bank',
            'iban' => fake()->optional()->iban('IT'),
            'notes' => fake()->optional()->sentence(),
            'is_primary' => false,
        ];
    }
}
