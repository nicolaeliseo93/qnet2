<?php

namespace App\DataObjects\Tags;

/**
 * Validated payload for creating a tag (POST /api/tags).
 *
 * Declared DTO (no "magic flying array") so the StoreTagRequest ->
 * TagService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 */
final readonly class CreateTagData
{
    public function __construct(
        public string $name,
    ) {}

    /**
     * Build from the validated StoreTagRequest payload.
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
