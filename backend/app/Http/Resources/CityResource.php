<?php

namespace App\Http\Resources;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin City
 *
 * Minimal projection of a city for the geo cascade selects (ADR 0010): id,
 * display name and the parent ids. `country_id` lets the cascade backfill the
 * full ancestor chain when a city is picked first (city-first selection).
 * Cities are read-only reference data.
 */
class CityResource extends JsonResource
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
            'state_id' => $this->state_id,
            'province_id' => $this->province_id,
        ];
    }
}
