<?php

namespace App\DataObjects\Companies;

use App\DataObjects\PersonalData\CreateAddress;

/**
 * Validated payload for creating a company (POST /api/companies).
 *
 * Declared DTO (no "magic flying array") so the StoreCompanyRequest →
 * CompanyService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `address` is null when the client did not submit the nested object at all
 * (a company may be created with no address yet); when present it becomes the
 * company's single primary address (AddressService::createFor).
 */
final readonly class CreateCompanyData
{
    public function __construct(
        public string $denomination,
        public ?string $vatNumber = null,
        public ?CreateAddress $address = null,
    ) {}

    /**
     * Build from the validated StoreCompanyRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            denomination: (string) $data['denomination'],
            vatNumber: $data['vat_number'] ?? null,
            address: self::buildAddress($data['address'] ?? null),
        );
    }

    public function hasAddress(): bool
    {
        return $this->address !== null;
    }

    /**
     * The company attributes for a mass-assignment create.
     *
     * @return array<string, string|null>
     */
    public function attributes(): array
    {
        return [
            'denomination' => $this->denomination,
            'vat_number' => $this->vatNumber,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $address
     */
    private static function buildAddress(?array $address): ?CreateAddress
    {
        if ($address === null) {
            return null;
        }

        return new CreateAddress(
            line1: (string) ($address['line1'] ?? ''),
            line2: $address['line2'] ?? null,
            postalCode: $address['postal_code'] ?? null,
            cityId: isset($address['city_id']) ? (int) $address['city_id'] : null,
            provinceId: isset($address['province_id']) ? (int) $address['province_id'] : null,
            stateId: isset($address['state_id']) ? (int) $address['state_id'] : null,
            countryId: isset($address['country_id']) ? (int) $address['country_id'] : null,
        );
    }
}
