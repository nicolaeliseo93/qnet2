<?php

namespace App\DataObjects\ReferentTypes;

/**
 * Validated payload for a partial (PATCH) referent type update
 * (PUT/PATCH /api/referent-types/{referentType}).
 *
 * Declared DTO (no "magic flying array") so the UpdateReferentTypeRequest ->
 * ReferentTypeService contract is explicit. A null `name` means the client
 * did NOT submit the key (leave it untouched).
 */
final readonly class UpdateReferentTypeData
{
    public function __construct(
        public ?string $name = null,
    ) {}

    /**
     * Build from the validated UpdateReferentTypeRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update (framework array boundary).
     *
     * @return array<string, string>
     */
    public function submittedAttributes(): array
    {
        return $this->name !== null ? ['name' => $this->name] : [];
    }
}
