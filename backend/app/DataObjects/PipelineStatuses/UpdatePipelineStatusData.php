<?php

namespace App\DataObjects\PipelineStatuses;

/**
 * Validated payload for a partial (PATCH) project status update
 * (PUT/PATCH /api/pipeline-statuses/{pipelineStatus}, spec 0023).
 *
 * Declared DTO (no "magic flying array") so the UpdatePipelineStatusRequest ->
 * PipelineStatusService contract is explicit. `color`/`group` are
 * legitimately nullable/optional VALUES (color clears back to none; group
 * is simply not always resubmitted on a partial PATCH), so a plain null
 * property cannot distinguish "not submitted" from "submitted as null" —
 * `colorSubmitted`/`groupSubmitted` carry that distinction explicitly. spec
 * 0039, D-5: `sort_order` is GONE from this DTO — server-managed, never
 * accepted from the client (see App\Services\Statuses\StatusOrderManager).
 * `group` (pivot, App\Enums\StatusGroup) — App\Services\Statuses\
 * SystemStatusGuard rejects it outright when the target row is a system
 * status.
 */
final readonly class UpdatePipelineStatusData
{
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
        public bool $colorSubmitted = false,
        public ?string $group = null,
        public bool $groupSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdatePipelineStatusRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            color: array_key_exists('color', $data) ? $data['color'] : null,
            colorSubmitted: array_key_exists('color', $data),
            group: array_key_exists('group', $data) ? (string) $data['group'] : null,
            groupSubmitted: array_key_exists('group', $data),
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

        if ($this->groupSubmitted) {
            $attributes['group'] = $this->group;
        }

        return $attributes;
    }
}
