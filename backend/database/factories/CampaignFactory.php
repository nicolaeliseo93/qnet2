<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * Default: a STANDALONE campaign (project_id null), so the three BR-2
     * classification fields plus `country_id` (BR-4, required standalone) are
     * filled here to keep the default state valid on its own. `state_id` is
     * built from the SAME country (spec 0027 BR-4: a level may only be set
     * when it belongs to its parent), `province_id`/`city_id` stay unset —
     * exactly as optional as they are for a standalone Project.
     *
     * The country is resolved to a real, persisted model FIRST (not left as an
     * unresolved `Factory` instance): `Factory::expandAttributes()` calls
     * `create()` on every unresolved `Factory` attribute it finds
     * INDEPENDENTLY, so reusing the SAME `Country::factory()` object both
     * directly (`country_id`) and inside `State::factory()->for(...)` would
     * silently create TWO different Country rows and leave `state.country_id`
     * pointing at a different country than `country_id` — a BR-4 violation.
     * Passing the already-created model's id to both keys guarantees a single
     * row.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $country = Country::factory()->create();

        return [
            'project_id' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'pipeline_status_id' => PipelineStatus::factory(),
            'business_function_id' => BusinessFunction::factory(),
            'country_id' => $country->id,
            'state_id' => State::factory()->state(['country_id' => $country->id]),
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
     * Link the campaign to a project: nulls out the three remaining BR-2
     * classification columns AND the four geo columns, mirroring what the
     * write pipeline enforces server-side. `state_id` left BR-2 for BR-5
     * (spec 0027, D-3): a linked campaign's geo is now a REFINEMENT of the
     * project's (a level the project fills is prohibited/NULL on the
     * campaign, a level it leaves empty is writable), so the default fixture
     * nulls every geo level rather than modelling one particular partial
     * inheritance — tests that need a specific refined level override it
     * explicitly after this state.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (): array => [
            'project_id' => $project->id,
            'pipeline_status_id' => null,
            'business_function_id' => null,
            'product_category_id' => null,
            'country_id' => null,
            'state_id' => null,
            'province_id' => null,
            'city_id' => null,
        ]);
    }
}
