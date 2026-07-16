<?php

namespace App\Http\Resources;

use App\Models\LeadStatus;
use App\Models\StatusGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeadStatus
 */
class LeadStatusResource extends JsonResource
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
            // spec 0039: system_key/status_group expose the two mandatory
            // system rows (D-2) and the optional classification (D-6). The
            // controller/TableDefinition eager-load `statusGroup` so this
            // never N+1s.
            'system_key' => $this->system_key,
            'status_group_id' => $this->status_group_id,
            'status_group' => $this->statusGroupSummary(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string, color: string|null}|null
     */
    private function statusGroupSummary(): ?array
    {
        /** @var StatusGroup|null $statusGroup */
        $statusGroup = $this->statusGroup;

        if ($statusGroup === null) {
            return null;
        }

        return ['id' => $statusGroup->id, 'name' => $statusGroup->name, 'color' => $statusGroup->color];
    }
}
