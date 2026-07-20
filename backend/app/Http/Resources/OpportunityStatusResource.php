<?php

namespace App\Http\Resources;

use App\Models\OpportunityStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OpportunityStatus
 */
class OpportunityStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            // spec 0043: the mandatory system rows (D-2) and the fixed
            // 3-value classification (`group`, App\Enums\StatusGroup).
            'system_key' => $this->system_key,
            'group' => $this->group->value,
            'created_at' => $this->created_at,
        ];
    }
}
