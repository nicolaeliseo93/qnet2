<?php

namespace Database\Factories;

use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowCriterion;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityWorkflowCriterion>
 */
class OpportunityWorkflowCriterionFactory extends Factory
{
    protected $model = OpportunityWorkflowCriterion::class;

    /**
     * Default `field`/`value_id` pair targets `source_id` (the simplest
     * allow-listed direct-column criterion, App\Support\OpportunityWorkflows\
     * CriterionFieldRegistry) — callers needing another field override both
     * keys via ->state().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opportunity_workflow_id' => OpportunityWorkflow::factory(),
            'field' => 'source_id',
            'value_id' => Source::factory(),
        ];
    }
}
