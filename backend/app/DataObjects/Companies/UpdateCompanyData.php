<?php

namespace App\DataObjects\Companies;

use App\DataObjects\PersonalData\CreateAddress;

/**
 * Validated payload for a partial (PATCH) company update
 * (PUT/PATCH /api/companies/{company}).
 *
 * Declared DTO (no "magic flying array") so the UpdateCompanyRequest →
 * CompanyService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `vat_number` is a legitimately nullable VALUE (clearing it), so a plain null
 * property cannot distinguish "not submitted" from "submitted as null" —
 * `vatNumberSubmitted` carries that distinction, mirroring
 * UpdateBusinessFunctionData's `*Submitted` flags. `address` presence rewrites
 * the company's single address (update if one exists, create otherwise);
 * absence leaves it untouched.
 */
final readonly class UpdateCompanyData
{
    public function __construct(
        public ?string $denomination = null,
        public ?string $vatNumber = null,
        public bool $vatNumberSubmitted = false,
        public ?CreateAddress $address = null,
        public bool $addressSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateCompanyRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            denomination: array_key_exists('denomination', $data) ? (string) $data['denomination'] : null,
            vatNumber: array_key_exists('vat_number', $data) ? $data['vat_number'] : null,
            vatNumberSubmitted: array_key_exists('vat_number', $data),
            address: array_key_exists('address', $data) ? self::buildAddress($data['address']) : null,
            addressSubmitted: array_key_exists('address', $data),
        );
    }

    /**
     * Whether an address was submitted with actual content (a submitted, but
     * null, `address` is treated as a no-op — there is no delete-address flow
     * in this slice).
     */
    public function hasAddress(): bool
    {
        return $this->addressSubmitted && $this->address !== null;
    }

    /**
     * Only the plain scalar attributes the client actually submitted, ready
     * for a partial mass-assignment update. `address` is handled separately by
     * the Service.
     *
     * @return array<string, string|null>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->denomination !== null) {
            $attributes['denomination'] = $this->denomination;
        }

        if ($this->vatNumberSubmitted) {
            $attributes['vat_number'] = $this->vatNumber;
        }

        return $attributes;
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
