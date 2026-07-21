<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\OpportunityWorkflowCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One matching criterion of an OpportunityWorkflow (spec 0047): `field` is an
 * allow-list key (App\Support\OpportunityWorkflows\CriterionFieldRegistry),
 * `value_id` the chosen value's id. A workflow matches an Opportunity only
 * when EVERY one of its criteria matches (AND, AC-013). No activity log on
 * this row (pure child collection of the workflow, which already logs its
 * own changes, mirroring OpportunityProductLine).
 */
#[Fillable(['opportunity_workflow_id', 'field', 'value_id'])]
class OpportunityWorkflowCriterion extends BaseModel
{
    /** @use HasFactory<OpportunityWorkflowCriterionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_id' => 'int',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(OpportunityWorkflow::class, 'opportunity_workflow_id');
    }
}
