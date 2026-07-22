<?php

namespace App\Http\Resources;

use App\Models\OpportunityWorkflow;
use App\Support\OpportunityWorkflows\CriterionValueLabelResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OpportunityWorkflow
 */
class OpportunityWorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $valueLabels = CriterionValueLabelResolver::resolve($this->criteria);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'criteria' => $this->criteria->map(fn ($criterion): array => [
                'id' => $criterion->id,
                'field' => $criterion->field,
                'value_id' => $criterion->value_id,
                'value_label' => $valueLabels[$criterion->id],
            ])->all(),
            'statuses' => $this->statuses->map(fn ($status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'description' => $status->description,
                'color' => $status->color,
                'sort_order' => $status->sort_order,
                'system_key' => $status->system_key,
                'group' => $status->group->value,
                'requires_note' => $status->requires_note,
            ])->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
