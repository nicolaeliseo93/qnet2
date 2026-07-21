<?php

namespace Database\Factories;

use App\Enums\StatusGroup;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityWorkflowStatus>
 */
class OpportunityWorkflowStatusFactory extends Factory
{
    protected $model = OpportunityWorkflowStatus::class;

    /** Incrementing counter backing `sort_order`, reset per factory instance. */
    private static int $nextSortOrder = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opportunity_workflow_id' => OpportunityWorkflow::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(['slate', 'green', 'red', 'blue']),
            'sort_order' => self::$nextSortOrder++,
            'system_key' => null,
            'group' => StatusGroup::Open,
        ];
    }

    /**
     * Marks the row as one of the two mandatory system rows ('open'/'closed',
     * spec 0047 AC-004), mirroring OpportunityStatusFactory::system().
     */
    public function system(string $key): static
    {
        return $this->state(fn () => match ($key) {
            'closed' => ['system_key' => 'closed', 'name' => 'Chiusa', 'sort_order' => 999, 'group' => StatusGroup::Closed],
            default => ['system_key' => 'open', 'name' => 'Aperta', 'sort_order' => 0, 'group' => StatusGroup::Open],
        });
    }

    /**
     * Places the row in the GLOBAL default set (opportunity_workflow_id
     * null, AC-005/AC-010) rather than under a specific workflow.
     */
    public function global(): static
    {
        return $this->state(fn () => ['opportunity_workflow_id' => null]);
    }
}
