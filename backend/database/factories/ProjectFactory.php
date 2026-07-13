<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'project_status_id' => ProjectStatus::factory(),
            'start_date' => null,
            'end_date' => null,
            'total_budget' => fake()->optional()->randomFloat(2, 1000, 500000),
            'target_lead' => fake()->optional()->numberBetween(1, 200),
        ];
    }

    /**
     * `code` (BR-1: PRJ-0001...) is service-generated in production and
     * deliberately NOT in the model's #[Fillable], so it must be assigned
     * directly (property assignment bypasses mass-assignment guarding) after
     * the instance is made, not through the fillable `definition()` array.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Project $project): void {
            $project->code ??= sprintf('PRJ-%04d', fake()->unique()->numberBetween(1, 999999));
        });
    }
}
