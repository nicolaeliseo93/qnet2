<?php

namespace App\DataObjects\LeadStatuses;

/**
 * Validated payload for creating a lead status (POST /api/lead-statuses,
 * spec 0029). Declared DTO (no "magic flying array") so the
 * StoreLeadStatusRequest -> LeadStatusService contract is explicit — see
 * standards/architecture.md -> Data Transfer Objects.
 */
final readonly class CreateLeadStatusData
{
    public function __construct(
        public string $name,
        public ?string $color = null,
        public int $sortOrder = 0,
    ) {}

    /**
     * Build from the validated StoreLeadStatusRequest payload.
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
