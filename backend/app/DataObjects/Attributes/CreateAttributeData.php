<?php

namespace App\DataObjects\Attributes;

/**
 * Validated payload for creating an attribute (POST /api/attributes, spec
 * 0017). Declared DTO (no "magic flying array") so the StoreAttributeRequest
 * → AttributeService contract is explicit — see standards/architecture.md →
 * Data Transfer Objects.
 *
 * `options` is null when the client did not submit the key at all (a
 * non-ENUM attribute never carries options); an explicit empty array would
 * already have failed FormRequest validation for an ENUM attribute.
 */
final readonly class CreateAttributeData
{
    /**
     * @param  array<int, array{value: string, label: string, sort_order?: int}>|null  $options
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $dataType,
        public ?array $options = null,
    ) {}

    /**
     * Build from the validated StoreAttributeRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            name: (string) $data['name'],
            dataType: (string) $data['data_type'],
            options: array_key_exists('options', $data) ? (array) $data['options'] : null,
        );
    }

    public function hasOptions(): bool
    {
        return $this->options !== null;
    }
}
