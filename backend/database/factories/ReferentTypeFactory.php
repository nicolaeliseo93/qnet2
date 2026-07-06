<?php

namespace Database\Factories;

use App\Models\ReferentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferentType>
 */
class ReferentTypeFactory extends Factory
{
    protected $model = ReferentType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
        ];
    }
}
