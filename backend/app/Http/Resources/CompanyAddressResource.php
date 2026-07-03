<?php

namespace App\Http\Resources;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 *
 * A company's single address, extending AddressResource's allowlist (id/geo
 * ids/line1/line2/postal_code/is_primary) with the geo NAMES (spec 0010):
 * unlike the plain AddressResource (ids only), the company detail view shows
 * the resolved country/region/province/city names without a second lookup.
 * Requires the geo relations eager-loaded (see CompanyService::
 * HYDRATED_RELATIONS) — no N+1 per company.
 */
class CompanyAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'postal_code' => $this->postal_code,
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'province_id' => $this->province_id,
            'city_id' => $this->city_id,
            'country' => $this->country?->name,
            'region' => $this->state?->name,
            'province' => $this->province?->name,
            'city' => $this->city?->name,
            'is_primary' => $this->is_primary,
        ];
    }
}
