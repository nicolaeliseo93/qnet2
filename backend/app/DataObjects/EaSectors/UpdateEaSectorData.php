<?php

namespace App\DataObjects\EaSectors;

/**
 * Validated payload for a partial (PATCH) EA sector update
 * (PUT/PATCH /api/ea-sectors/{eaSector}, spec 0018).
 *
 * Declared DTO (no "magic flying array") so the UpdateEaSectorRequest →
 * EaSectorService contract is explicit. `parent_id` is a legitimately
 * nullable VALUE (moving to root), so a plain null property cannot
 * distinguish "not submitted" from "submitted as null" — the
 * `parentIdSubmitted` flag carries that distinction explicitly, mirroring
 * UpdateProductCategoryData.
 */
final readonly class UpdateEaSectorData
{
    public function __construct(
        public ?string $name = null,
        public ?int $parentId = null,
        public bool $parentIdSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateEaSectorRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            parentId: array_key_exists('parent_id', $data) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null,
            parentIdSubmitted: array_key_exists('parent_id', $data),
        );
    }

    public function hasParentId(): bool
    {
        return $this->parentIdSubmitted;
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->parentIdSubmitted) {
            $attributes['parent_id'] = $this->parentId;
        }

        return $attributes;
    }
}
