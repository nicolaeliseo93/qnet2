<?php

namespace App\DataObjects\EaSectors;

/**
 * Validated payload for creating an EA sector (POST /api/ea-sectors,
 * spec 0018). Declared DTO (no "magic flying array") so the
 * StoreEaSectorRequest → EaSectorService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * `tagIds` (spec 0019) is a to-many reference, synced by EaSectorService
 * post-create via `tags()->sync()` — it is NOT a mass-assignable column, so
 * it stays out of attributes().
 */
final readonly class CreateEaSectorData
{
    /**
     * @param  array<int, int>|null  $tagIds
     */
    public function __construct(
        public string $name,
        public ?int $parentId = null,
        public ?array $tagIds = null,
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
            tagIds: array_key_exists('tag_ids', $data) ? array_map('intval', $data['tag_ids']) : null,
        );
    }

    public function hasTagIds(): bool
    {
        return $this->tagIds !== null;
    }
}
