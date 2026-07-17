<?php

namespace App\Http\Resources;

use App\Enums\GeoScopeLevel;
use App\Models\Project;
use App\Support\Geo\GeoNameLocalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalBudget = $this->total_budget;
        $allocatedBudget = (float) ($this->allocated_budget_sum ?? 0);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'pipeline_status_id' => $this->pipeline_status_id,
            'pipeline_status' => $this->pipelineStatus !== null
                ? ['id' => $this->pipelineStatus->id, 'name' => $this->pipelineStatus->name, 'color' => $this->pipelineStatus->color]
                : null,
            'source_id' => $this->source_id,
            'source' => $this->summarize($this->source),
            'business_function_id' => $this->business_function_id,
            'business_function' => $this->summarize($this->businessFunction),
            'country_id' => $this->country_id,
            'country' => $this->summarize($this->country, geo: true),
            'state_id' => $this->state_id,
            'state' => $this->summarize($this->state, geo: true),
            'province_id' => $this->province_id,
            'province' => $this->summarize($this->province, geo: true),
            'city_id' => $this->city_id,
            'city' => $this->summarize($this->city, geo: true),
            'geo_scope' => GeoScopeLevel::for($this->country_id, $this->state_id, $this->province_id, $this->city_id)?->value,
            'product_category_id' => $this->product_category_id,
            'product_category' => $this->summarize($this->productCategory),
            'partner_id' => $this->partner_id,
            'partner' => $this->summarize($this->partner),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_budget' => $totalBudget,
            'target_lead' => $this->target_lead,
            // BR-7: computed budget visibility, never blocking the write (D-4).
            'allocated_budget' => $this->formatMoney($allocatedBudget),
            'remaining_budget' => $totalBudget === null ? null : $this->formatMoney((float) $totalBudget - $allocatedBudget),
            'campaigns_count' => (int) ($this->campaigns_count ?? 0),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * A related row projected to {id, name}. `$geo` localizes the name to
     * Italian (country/state/province/city only) — never applied to the other
     * relations, whose names are user data (a company could be named "Milan").
     *
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related, bool $geo = false): ?array
    {
        if ($related === null) {
            return null;
        }

        $name = $geo ? GeoNameLocalizer::toItalian($related->name) : $related->name;

        return ['id' => $related->id, 'name' => $name];
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
