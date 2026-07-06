<?php

namespace App\DataObjects\PersonalData;

/**
 * Declared payload for creating/updating an Address (see
 * standards/architecture.md → Data Transfer Objects). Built at the HTTP
 * boundary by the FormRequest and consumed by AddressService — the service
 * reads typed properties, never a "magic flying array".
 *
 * Latitude/longitude travel as strings to preserve the exact decimal the client
 * submitted (the model casts them to decimal:8); the FormRequest guarantees they
 * are numeric and within range.
 */
final readonly class CreateAddress
{
    public function __construct(
        public string $line1,
        public ?string $line2 = null,
        public ?string $postalCode = null,
        public ?int $cityId = null,
        public ?int $provinceId = null,
        public ?int $stateId = null,
        public ?int $countryId = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
        public bool $isPrimary = false,
    ) {}

    /**
     * The attributes ready for mass assignment on the Address model. Forwarded
     * verbatim to Eloquent (allowed framework-array exception).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'line1' => $this->line1,
            'line2' => $this->line2,
            'postal_code' => $this->postalCode,
            'city_id' => $this->cityId,
            'province_id' => $this->provinceId,
            'state_id' => $this->stateId,
            'country_id' => $this->countryId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_primary' => $this->isPrimary,
        ];
    }
}
