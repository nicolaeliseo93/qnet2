<?php

namespace App\Http\Resources;

use App\Enums\GeoScopeLevel;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Card-grid projection of a Project (GET /api/projects, spec 0026, D-3).
 * Deliberately separate from ProjectResource (the detail-page payload): the
 * card payload carries per-item `can` affordances (BR-2), which the detail
 * page does not need.
 *
 * Relies on the caller (ProjectService::index) having eager-loaded
 * pipeline_status and the campaigns_count/leads_count aggregates via
 * withCount, so resolving this never N+1s.
 *
 * @mixin Project
 */
class ProjectCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $leadsCount = (int) $this->leads_count;
        $totalBudget = $this->total_budget;
        $allocatedBudget = (float) ($this->allocated_budget_sum ?? 0);
        $actor = $request->user();
        $geoScope = GeoScopeLevel::for($this->country_id, $this->state_id, $this->province_id, $this->city_id);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'pipeline_status' => $this->pipelineStatus !== null
                ? ['id' => $this->pipelineStatus->id, 'name' => $this->pipelineStatus->name, 'color' => $this->pipelineStatus->color]
                : null,
            'geo_scope' => $geoScope?->value,
            'geo_label' => $this->geoLabel($geoScope),
            'campaigns_count' => (int) $this->campaigns_count,
            'leads_count' => $leadsCount,
            'total_budget' => $totalBudget === null ? null : $this->formatMoney((float) $totalBudget),
            'allocated_budget' => $this->formatMoney($allocatedBudget),
            'remaining_budget' => $totalBudget === null ? null : $this->formatMoney((float) $totalBudget - $allocatedBudget),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'can' => [
                'update' => $actor !== null && Gate::forUser($actor)->allows('update', $this->resource),
                'delete' => $actor !== null && Gate::forUser($actor)->allows('delete', $this->resource),
            ],
        ];
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * The finest non-null geo level's own NAME (D-2), e.g. "Milano" for a
     * city scope, "Italia" for a country scope — a single compact badge
     * label with no second request.
     */
    private function geoLabel(?GeoScopeLevel $scope): ?string
    {
        /** @var Model|null $related */
        $related = match ($scope) {
            GeoScopeLevel::City => $this->city,
            GeoScopeLevel::Province => $this->province,
            GeoScopeLevel::State => $this->state,
            GeoScopeLevel::Country => $this->country,
            null => null,
        };

        return $related?->name;
    }
}
