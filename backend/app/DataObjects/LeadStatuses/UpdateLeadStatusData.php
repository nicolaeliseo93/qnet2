<?php

namespace App\DataObjects\LeadStatuses;

/**
 * Validated payload for a partial (PATCH) lead status update
 * (PUT/PATCH /api/lead-statuses/{leadStatus}, spec 0029).
 *
 * Declared DTO (no "magic flying array") so the UpdateLeadStatusRequest ->
 * LeadStatusService contract is explicit. `color`/`status_group_id` are
 * legitimately nullable VALUES (clearing them back to none), so a plain null
 * property cannot distinguish "not submitted" from "submitted as null" —
 * `colorSubmitted`/`statusGroupIdSubmitted` carry that distinction
 * explicitly, mirroring UpdatePipelineStatusData's same pairs. spec 0039,
 * D-5: `sort_order` is GONE from this DTO — server-managed, never accepted
 * from the client (see App\Services\Statuses\StatusOrderManager).
 * `status_group_id` (D-6) is the new field; App\Services\Statuses\
 * SystemStatusGuard rejects it outright when the target row is a system
 * status.
 */
final readonly class UpdateLeadStatusData
{
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
        public bool $colorSubmitted = false,
        public ?int $statusGroupId = null,
        public bool $statusGroupIdSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateLeadStatusRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            color: array_key_exists('color', $data) ? $data['color'] : null,
            colorSubmitted: array_key_exists('color', $data),
            statusGroupId: array_key_exists('status_group_id', $data) && $data['status_group_id'] !== null
                ? (int) $data['status_group_id']
                : null,
            statusGroupIdSubmitted: array_key_exists('status_group_id', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update (framework array boundary).
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->colorSubmitted) {
            $attributes['color'] = $this->color;
        }

        if ($this->statusGroupIdSubmitted) {
            $attributes['status_group_id'] = $this->statusGroupId;
        }

        return $attributes;
    }
}
