<?php

namespace App\DataObjects\Referents;

/**
 * Validated payload for creating a referent (POST /api/referents).
 *
 * Declared DTO (no "magic flying array") so the StoreReferentRequest ->
 * ReferentService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `name` is intentionally absent: it is derived server-side from the
 * required nested personal-data card (mirrors CreateUserData, ADR 0012/0013).
 */
final readonly class CreateReferentData
{
    public function __construct(
        public ?int $referentTypeId,
        public string $contactScope,
        public ?string $notes,
    ) {}

    /**
     * Build from the validated StoreReferentRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            referentTypeId: isset($data['referent_type_id']) ? (int) $data['referent_type_id'] : null,
            contactScope: (string) $data['contact_scope'],
            notes: $data['notes'] ?? null,
        );
    }

    /**
     * The referent attributes for a mass-assignment create (framework array
     * boundary). `name` is NOT included here: it is derived from the
     * personal-data card and merged in by the ReferentService.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'referent_type_id' => $this->referentTypeId,
            'contact_scope' => $this->contactScope,
            'notes' => $this->notes,
        ];
    }
}
