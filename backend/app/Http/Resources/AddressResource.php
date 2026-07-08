<?php

namespace App\Http\Resources;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 *
 * Explicit output allowlist for an address. The locating parts (line1, line2,
 * latitude, longitude) are hidden on the model — kept out of the activity log
 * and default serialization — but are deliberately re-exposed here: a serialized
 * address without them is useless to the frontend. Property access bypasses
 * $hidden, so no makeVisible() is needed — this projection IS the conscious,
 * authorized (addresses.view) re-exposure.
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'postal_code' => $this->postal_code,
            'site_type' => $this->site_type,
            'city_id' => $this->city_id,
            'province_id' => $this->province_id,
            'state_id' => $this->state_id,
            'country_id' => $this->country_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_primary' => $this->is_primary,
            'addressable_type' => $this->addressable_type,
            'addressable_id' => $this->addressable_id,
            'created_at' => $this->created_at,
        ];
    }
}
