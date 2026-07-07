<?php

namespace App\DataObjects\Sources;

/**
 * Validated payload for creating a source (POST /api/sources).
 *
 * Declared DTO (no "magic flying array") so the StoreSourceRequest ->
 * SourceService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 */
final readonly class CreateSourceData
{
    public function __construct(
        public string $name,
    ) {}

    /**
     * Build from the validated StoreSourceRequest payload.
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
