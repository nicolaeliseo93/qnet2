<?php

namespace Database\Factories;

use App\Models\OpportunityWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityWorkflow>
 */
class OpportunityWorkflowFactory extends Factory
{
    protected $model = OpportunityWorkflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'is_active' => true,
            'criteria_signature' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
