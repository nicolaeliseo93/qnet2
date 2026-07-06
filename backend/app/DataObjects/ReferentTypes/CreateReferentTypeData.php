<?php

namespace App\DataObjects\ReferentTypes;

/**
 * Validated payload for creating a referent type (POST /api/referent-types).
 *
 * Declared DTO (no "magic flying array") so the StoreReferentTypeRequest ->
 * ReferentTypeService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 */
final readonly class CreateReferentTypeData
{
    public function __construct(
        public string $name,
    ) {}

    /**
     * Build from the validated StoreReferentTypeRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(name: (string) $data['name']);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => $this->name];
    }
}
