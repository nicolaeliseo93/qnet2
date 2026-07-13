<?php

namespace App\Http\Resources;

use App\Models\Project;
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
            'registry_id' => $this->registry_id,
            'registry' => $this->summarize($this->registry),
            'project_status_id' => $this->project_status_id,
            'project_status' => $this->projectStatus !== null
                ? ['id' => $this->projectStatus->id, 'name' => $this->projectStatus->name, 'color' => $this->projectStatus->color]
                : null,
            'source_id' => $this->source_id,
            'source' => $this->summarize($this->source),
            'business_function_id' => $this->business_function_id,
            'business_function' => $this->summarize($this->businessFunction),
            'state_id' => $this->state_id,
            'state' => $this->summarize($this->state),
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
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return ['id' => $related->id, 'name' => $related->name];
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
