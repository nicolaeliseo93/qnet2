<?php

namespace App\DataObjects\OpportunityStatuses;

/**
 * Validated payload for creating an opportunity status (POST
 * /api/opportunity-statuses, spec 0043). Declared DTO (no "magic flying
 * array") so the StoreOpportunityStatusRequest -> OpportunityStatusService
 * contract is explicit — see standards/architecture.md -> Data Transfer
 * Objects.
 *
 * `sort_order` is GONE from this DTO — server-managed, placed by
 * App\Services\Statuses\StatusOrderManager::placeNew() inside
 * OpportunityStatusService::create(), never accepted from the client.
 * `group` (App\Enums\StatusGroup) is REQUIRED — every row, system or
 * custom, carries a classification.
 */
final readonly class CreateOpportunityStatusData
{
    public function __construct(
        public string $name,
        public ?string $color,
        public string $group,
    ) {}

    /**
     * Build from the validated StoreOpportunityStatusRequest payload.
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
