<?php

namespace App\Http\Resources;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Country
 *
 * Minimal projection of a country for the geo cascade selects (ADR 0010): just
 * the id, the display name and the ISO2 code the frontend needs to populate the
 * first dropdown. Countries are read-only reference data.
 */
class CountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'iso2' => $this->iso2,
        ];
    }
}
