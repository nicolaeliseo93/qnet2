<?php

namespace App\Http\Resources;

use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Province
 *
 * Minimal projection of a province for the geo cascade selects (ADR 0010): id,
 * display name and the parent state_id the frontend uses to chain the next
 * dropdown. Provinces are read-only reference data.
 */
class ProvinceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->localizedName(),
            'state_id' => $this->state_id,
        ];
    }
}
