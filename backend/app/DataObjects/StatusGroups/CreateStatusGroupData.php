<?php

namespace App\DataObjects\StatusGroups;

/**
 * Validated payload for creating a status group (POST /api/status-groups,
 * spec 0039). Declared DTO (no "magic flying array") so the
 * StoreStatusGroupRequest -> StatusGroupService contract is explicit — see
 * standards/architecture.md -> Data Transfer Objects.
 */
final readonly class CreateStatusGroupData
{
    public function __construct(
        public string $name,
        public ?string $color = null,
        public int $sortOrder = 0,
    ) {}

    /**
     * Build from the validated StoreStatusGroupRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            color: array_key_exists('color', $data) ? $data['color'] : null,
            sortOrder: array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
            'sort_order' => $this->sortOrder,
        ];
    }
}
