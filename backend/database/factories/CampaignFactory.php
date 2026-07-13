<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * Default: a STANDALONE campaign (project_id null), so the four derived
     * columns (BR-2) are required and filled here to keep the default state
     * valid on its own.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'project_status_id' => ProjectStatus::factory(),
            'business_function_id' => BusinessFunction::factory(),
            'state_id' => State::factory(),
            'product_category_id' => ProductCategory::factory(),
            'start_date' => null,
            'end_date' => null,
            'total_budget' => fake()->optional()->randomFloat(2, 100, 50000),
            'target_lead' => fake()->optional()->numberBetween(1, 100),
        ];
    }

    /**
     * `code` (BR-1: CMP-0001...) is service-generated in production and
     * deliberately NOT in the model's #[Fillable], so it must be assigned
     * directly (property assignment bypasses mass-assignment guarding) after
     * the instance is made, not through the fillable `definition()` array.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Campaign $campaign): void {
            $campaign->code ??= sprintf('CMP-%04d', fake()->unique()->numberBetween(1, 999999));
        });
    }

    /**
     * Link the campaign to a project: the four derived columns (BR-2) are
     * nulled out here, mirroring what the write pipeline enforces server-side
     * (their effective value is then read through the linked project).
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (): array => [
            'project_id' => $project->id,
            'project_status_id' => null,
            'business_function_id' => null,
            'state_id' => null,
            'product_category_id' => null,
        ]);
    }
}
