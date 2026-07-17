<?php

namespace App\Http\Resources;

use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin State
 *
 * Minimal projection of a state for the geo cascade selects (ADR 0010): id,
 * display name and the parent country_id the frontend uses to chain the next
 * dropdown. States are read-only reference data.
 */
class StateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->localizedName(),
            'country_id' => $this->country_id,
        ];
    }
}
