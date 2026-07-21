<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\OpportunityWorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opportunity workflow configurator (spec 0047): a named, activatable set of
 * matching criteria (`criteria()`) plus its own working-state statuses
 * (`statuses()`) applied to an Opportunity. `criteria_signature` is the
 * deterministic "field:value_id|..." string that enforces criteria-
 * combination uniqueness (AC-009) — computed/written only by the service
 * that syncs a workflow's criteria, DELIBERATELY absent from #[Fillable].
 */
#[Fillable(['name', 'is_active'])]
class OpportunityWorkflow extends BaseModel
{
    /** @use HasFactory<OpportunityWorkflowFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    /**
     * @return HasMany<OpportunityWorkflowCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(OpportunityWorkflowCriterion::class);
    }

    /**
     * @return HasMany<OpportunityWorkflowStatus, $this>
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(OpportunityWorkflowStatus::class);
    }
}
