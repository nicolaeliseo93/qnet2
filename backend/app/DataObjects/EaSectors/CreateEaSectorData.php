<?php

namespace App\DataObjects\EaSectors;

/**
 * Validated payload for creating an EA sector (POST /api/ea-sectors,
 * spec 0018). Declared DTO (no "magic flying array") so the
 * StoreEaSectorRequest → EaSectorService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 */
final readonly class CreateEaSectorData
{
    public function __construct(
        public string $name,
        public ?int $parentId = null,
    ) {}

    /**
     * Build from the validated StoreEaSectorRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            parentId: array_key_exists('parent_id', $data) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null,
        );
    }
}
