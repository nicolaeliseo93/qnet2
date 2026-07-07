<?php

namespace App\DataObjects\Attributes;

/**
 * Validated payload for a partial (PATCH) attribute update
 * (PUT/PATCH /api/attributes/{attribute}, spec 0017).
 *
 * Declared DTO (no "magic flying array") so the UpdateAttributeRequest →
 * AttributeService contract is explicit — see standards/architecture.md →
 * Data Transfer Objects. `options`, when submitted, is a FULL-REPLACE of the
 * attribute's option list (spec 0017); `hasDataType()`/`hasOptions()` expose
 * "submitted at all" so the Service can distinguish it from "not touched".
 */
final readonly class UpdateAttributeData
{
    /**
     * @param  array<int, array{value: string, label: string, sort_order?: int}>|null  $options
     */
    public function __construct(
        public ?string $code = null,
        public ?string $name = null,
        public ?string $dataType = null,
        public ?array $options = null,
    ) {}

    /**
     * Build from the validated UpdateAttributeRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            code: array_key_exists('code', $data) ? (string) $data['code'] : null,
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            dataType: array_key_exists('data_type', $data) ? (string) $data['data_type'] : null,
            options: array_key_exists('options', $data) ? (array) $data['options'] : null,
        );
    }

    public function hasDataType(): bool
    {
        return $this->dataType !== null;
    }

    public function hasOptions(): bool
    {
        return $this->options !== null;
    }

    /**
     * Only the plain scalar attributes the client actually submitted, ready
     * for a partial mass-assignment update. `data_type` is handled separately
     * by the Service (immutability guard + enum cast); `options` is
     * full-replace synced separately.
     *
     * @return array<string, string>
     */
    public function submittedAttributes(): array
    {
        return array_filter(
            ['code' => $this->code, 'name' => $this->name],
            static fn (?string $value): bool => $value !== null,
        );
    }
}
