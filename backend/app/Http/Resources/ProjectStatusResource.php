<?php

namespace App\Http\Resources;

use App\Models\ProjectStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProjectStatus
 */
class ProjectStatusResource extends JsonResource
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
            'created_at' => $this->created_at,
        ];
    }
}
