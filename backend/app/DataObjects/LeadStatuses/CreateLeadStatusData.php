<?php

namespace App\DataObjects\LeadStatuses;

/**
 * Validated payload for creating a lead status (POST /api/lead-statuses,
 * spec 0029). Declared DTO (no "magic flying array") so the
 * StoreLeadStatusRequest -> LeadStatusService contract is explicit — see
 * standards/architecture.md -> Data Transfer Objects.
 *
 * spec 0039, D-5: `sort_order` is GONE from this DTO — server-managed,
 * placed by App\Services\Statuses\StatusOrderManager::placeNew() inside
 * LeadStatusService::create(), never accepted from the client. `group`
 * (pivot, App\Enums\StatusGroup) is REQUIRED — every row, system or custom,
 * carries a classification.
 */
final readonly class CreateLeadStatusData
{
    public function __construct(
        public string $name,
        public ?string $color,
        public string $group,
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
            group: (string) $data['group'],
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
            'group' => $this->group,
        ];
    }
}
