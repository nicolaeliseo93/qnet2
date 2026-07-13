<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Campaign
 *
 * BR-2: when the campaign is linked to a project (`project_id` set), its own
 * 4 classification columns are NULL in DB — this Resource always exposes the
 * EFFECTIVE values (read through the project when linked), plus the
 * `derived_from_project` flag the frontend uses to render them read-only.
 * Relies on CampaignService::loadDetail() having eager-loaded both branches
 * (own classification + project.classification), so resolving either side
 * here never N+1s.
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $project = $this->project;
        $derivedFromProject = $project !== null;

        $projectStatus = $derivedFromProject ? $project->projectStatus : $this->projectStatus;
        $businessFunction = $derivedFromProject ? $project->businessFunction : $this->businessFunction;
        $state = $derivedFromProject ? $project->state : $this->state;
        $productCategory = $derivedFromProject ? $project->productCategory : $this->productCategory;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'project_id' => $this->project_id,
            'project' => $project === null ? null : [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
            ],
            'name' => $this->name,
            'description' => $this->description,
            'registry_id' => $this->registry_id,
            'registry' => $this->summarize($this->registry),
            'source_id' => $this->source_id,
            'source' => $this->summarize($this->source),
            'partner_id' => $this->partner_id,
            'partner' => $this->summarize($this->partner),
            'derived_from_project' => $derivedFromProject,
            'project_status_id' => $projectStatus?->id,
            'project_status' => $projectStatus === null ? null : [
                'id' => $projectStatus->id,
                'name' => $projectStatus->name,
                'color' => $projectStatus->color,
            ],
            'business_function_id' => $businessFunction?->id,
            'business_function' => $this->summarize($businessFunction),
            'state_id' => $state?->id,
            'state' => $this->summarize($state),
            'product_category_id' => $productCategory?->id,
            'product_category' => $this->summarize($productCategory),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_budget' => $this->total_budget,
            'target_lead' => $this->target_lead,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return ['id' => $related->id, 'name' => $related->name];
    }
}
