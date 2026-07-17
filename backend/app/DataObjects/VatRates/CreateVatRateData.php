<?php

namespace App\DataObjects\VatRates;

/**
 * Validated payload for creating a VAT rate (POST /api/vat-rates).
 *
 * Declared DTO (no "magic flying array") so the StoreVatRateRequest ->
 * VatRateService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 */
final readonly class CreateVatRateData
{
    public function __construct(
        public string $name,
        public float $rate,
    ) {}

    /**
     * Build from the validated StoreVatRateRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            rate: (float) $data['rate'],
        );
    }

    /**
     * @return array<string, string|float>
     */
    public function attributes(): array
    {
        return ['name' => $this->name, 'rate' => $this->rate];
    }
}
