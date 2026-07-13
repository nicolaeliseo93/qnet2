<?php

namespace App\DataObjects\ProjectStatuses;

/**
 * Validated payload for creating a project status (POST /api/project-statuses,
 * spec 0023). Declared DTO (no "magic flying array") so the
 * StoreProjectStatusRequest -> ProjectStatusService contract is explicit —
 * see standards/architecture.md -> Data Transfer Objects.
 */
final readonly class CreateProjectStatusData
{
    public function __construct(
        public string $name,
        public ?string $color = null,
        public int $sortOrder = 0,
    ) {}

    /**
     * Build from the validated StoreProjectStatusRequest payload.
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
