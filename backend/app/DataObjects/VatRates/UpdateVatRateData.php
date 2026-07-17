<?php

namespace App\DataObjects\VatRates;

/**
 * Validated payload for a partial (PATCH) VAT rate update
 * (PUT/PATCH /api/vat-rates/{vatRate}).
 *
 * Declared DTO (no "magic flying array") so the UpdateVatRateRequest ->
 * VatRateService contract is explicit. The `*Submitted` flags distinguish
 * "not submitted" from "submitted", mirroring UpdateProductData.
 */
final readonly class UpdateVatRateData
{
    public function __construct(
        public ?string $name = null,
        public bool $nameSubmitted = false,
        public ?float $rate = null,
        public bool $rateSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateVatRateRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            nameSubmitted: array_key_exists('name', $data),
            rate: array_key_exists('rate', $data) ? (float) $data['rate'] : null,
            rateSubmitted: array_key_exists('rate', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update.
     *
     * @return array<string, string|float>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->nameSubmitted) {
            $attributes['name'] = $this->name;
        }

        if ($this->rateSubmitted) {
            $attributes['rate'] = $this->rate;
        }

        return $attributes;
    }
}
