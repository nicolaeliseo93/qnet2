<?php

namespace App\DataObjects\Registries;

/**
 * Validated payload for creating a registry (POST /api/registries, spec 0020).
 *
 * Declared DTO (no "magic flying array") so the StoreRegistryRequest ->
 * RegistryService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `name` is intentionally absent: it is derived server-side from the
 * required nested personal-data card (mirrors CreateReferentData).
 * `eaSectorIds`/`referentIds`/`managerIds` are to-many references, synced by
 * RegistryService post-create via `->sync()` — they are NOT mass-assignable
 * columns, so they stay out of attributes() (mirrors CreateEaSectorData's
 * tagIds).
 */
final readonly class CreateRegistryData
{
    /**
     * @param  array<int, int>|null  $eaSectorIds
     * @param  array<int, int>|null  $referentIds
     * @param  array<int, int>|null  $managerIds
     */
    public function __construct(
        public ?int $sourceId,
        public ?array $eaSectorIds,
        public ?array $referentIds,
        public ?array $managerIds,
        public ?int $supervisorId,
        public ?int $commercialId,
        public ?int $reporterId,
        public ?string $vatGroup,
        public bool $isSupplier,
        public bool $isQualifiedSupplier,
        public ?string $agreementStatus,
        public ?string $agreementNotes,
        public ?string $sizeClass,
        public ?int $employeeCount,
    ) {}

    /**
     * Build from the validated StoreRegistryRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            eaSectorIds: array_key_exists('ea_sector_ids', $data) ? array_map('intval', $data['ea_sector_ids']) : null,
            referentIds: array_key_exists('referent_ids', $data) ? array_map('intval', $data['referent_ids']) : null,
            managerIds: array_key_exists('manager_ids', $data) ? array_map('intval', $data['manager_ids']) : null,
            supervisorId: isset($data['supervisor_id']) ? (int) $data['supervisor_id'] : null,
            commercialId: isset($data['commercial_id']) ? (int) $data['commercial_id'] : null,
            reporterId: isset($data['reporter_id']) ? (int) $data['reporter_id'] : null,
            vatGroup: $data['vat_group'] ?? null,
            isSupplier: (bool) ($data['is_supplier'] ?? false),
            isQualifiedSupplier: (bool) ($data['is_qualified_supplier'] ?? false),
            agreementStatus: $data['agreement_status'] ?? null,
            agreementNotes: $data['agreement_notes'] ?? null,
            sizeClass: $data['size_class'] ?? null,
            employeeCount: isset($data['employee_count']) ? (int) $data['employee_count'] : null,
        );
    }

    public function hasEaSectorIds(): bool
    {
        return $this->eaSectorIds !== null;
    }

    public function hasReferentIds(): bool
    {
        return $this->referentIds !== null;
    }

    public function hasManagerIds(): bool
    {
        return $this->managerIds !== null;
    }

    /**
     * The registry's own scalar attributes for a mass-assignment create
     * (framework array boundary). `name` is NOT included here: it is derived
     * from the personal-data card and merged in by the RegistryService, which
     * also normalizes `is_qualified_supplier` against `is_supplier`.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'source_id' => $this->sourceId,
            'vat_group' => $this->vatGroup,
            'is_supplier' => $this->isSupplier,
            'is_qualified_supplier' => $this->isQualifiedSupplier,
            'agreement_status' => $this->agreementStatus,
            'agreement_notes' => $this->agreementNotes,
            'size_class' => $this->sizeClass,
            'supervisor_id' => $this->supervisorId,
            'commercial_id' => $this->commercialId,
            'reporter_id' => $this->reporterId,
            'employee_count' => $this->employeeCount,
        ];
    }
}
