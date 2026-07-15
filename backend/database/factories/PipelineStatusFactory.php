<?php

namespace Database\Factories;

use App\Models\PipelineStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineStatus>
 */
class PipelineStatusFactory extends Factory
{
    protected $model = PipelineStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->optional()->hexColor(),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
