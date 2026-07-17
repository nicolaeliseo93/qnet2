<?php

namespace App\Http\Resources;

use App\Models\Address;
use App\Support\Geo\GeoNameLocalizer;
use Illuminate\Database\Eloquent\Model;
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
 *
 * The city/province/state/country NAMES are emitted as {id, name} refs ONLY
 * when the caller eager-loaded the relation (whenLoaded): the raw *_id columns
 * are always present, so a consumer that does not need the human-readable
 * location pays no N+1, while the read views that load them (e.g. the registry
 * detail tree) render the full address.
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
            'city' => $this->whenLoaded('city', fn (): ?array => $this->toGeoRef($this->city)),
            'province' => $this->whenLoaded('province', fn (): ?array => $this->toGeoRef($this->province)),
            'state' => $this->whenLoaded('state', fn (): ?array => $this->toGeoRef($this->state)),
            'country' => $this->whenLoaded('country', fn (): ?array => $this->toGeoRef($this->country)),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_primary' => $this->is_primary,
            'addressable_type' => $this->addressable_type,
            'addressable_id' => $this->addressable_id,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * A geo relation (city/province/state/country) projected to {id, name}, or
     * null when the foreign key is unset (the relation loaded as null).
     *
     * @return array{id: int, name: string}|null
     */
    private function toGeoRef(?Model $related): ?array
    {
        return $related !== null ? ['id' => $related->id, 'name' => GeoNameLocalizer::toItalian($related->name)] : null;
    }
}
