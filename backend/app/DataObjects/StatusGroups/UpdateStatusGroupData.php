<?php

namespace App\DataObjects\StatusGroups;

/**
 * Validated payload for a partial (PATCH) status group update
 * (PUT/PATCH /api/status-groups/{statusGroup}, spec 0039).
 *
 * Declared DTO (no "magic flying array") so the UpdateStatusGroupRequest ->
 * StatusGroupService contract is explicit. `color` is a legitimately nullable
 * VALUE (clearing it back to none), so a plain null property cannot
 * distinguish "not submitted" from "submitted as null" — `colorSubmitted`
 * carries that distinction explicitly, mirroring UpdateLeadStatusData's same
 * pair. `sort_order` is never legitimately null (schema default 0, not
 * nullable), so a plain null sentinel is enough to mean "not submitted".
 */
final readonly class UpdateStatusGroupData
{
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
        public bool $colorSubmitted = false,
        public ?int $sortOrder = null,
    ) {}

    /**
     * Build from the validated UpdateStatusGroupRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            color: array_key_exists('color', $data) ? $data['color'] : null,
            colorSubmitted: array_key_exists('color', $data),
            sortOrder: array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : null,
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

        if ($this->sortOrder !== null) {
            $attributes['sort_order'] = $this->sortOrder;
        }

        return $attributes;
    }
}
