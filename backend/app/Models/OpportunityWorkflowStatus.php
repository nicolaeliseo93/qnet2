<?php

namespace App\Models;

use App\Enums\WorkflowStatusGroup;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\OpportunityWorkflowStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Workflow status lookup entity (spec 0047): the "stato di lavorazione" pick
 * list row for an Opportunity — a NEW dimension, distinct from
 * `OpportunityStatus` (sales pipeline, spec 0043). Belongs to a workflow
 * (`opportunity_workflow_id`) OR to the GLOBAL default set when null
 * (AC-005/AC-010). `system_key`/`opportunity_workflow_id` are DELIBERATELY
 * absent from #[Fillable] — never mass-assignable, written only by the
 * migration seed and the service that creates/syncs a workflow's status set.
 * `group` (App\Enums\WorkflowStatusGroup) classifies the row as
 * open/pending/closed_won/closed_lost — the closed phase carries its outcome.
 */
#[Fillable(['name', 'color', 'sort_order', 'group'])]
class OpportunityWorkflowStatus extends BaseModel
{
    /** @use HasFactory<OpportunityWorkflowStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'group' => WorkflowStatusGroup::class,
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(OpportunityWorkflow::class, 'opportunity_workflow_id');
    }

    /**
     * Whether this is a pinned system row ('open'/'closed_won'/'closed_lost',
     * AC-004) rather than a custom, user-created status.
     */
    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
