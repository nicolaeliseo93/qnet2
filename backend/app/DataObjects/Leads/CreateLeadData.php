<?php

declare(strict_types=1);

namespace App\DataObjects\Leads;

/**
 * Validated payload for creating a lead (POST /api/leads, spec 0024).
 *
 * Declared DTO (no "magic flying array") so the StoreLeadRequest ->
 * LeadService contract is explicit — see standards/architecture.md → Data
 * Transfer Objects. `registry_id`/`campaign_id` are mandatory (BR-1, spec
 * 0041 D-1); no `code` field exists for a Lead (D-3). `extra_fields` (spec
 * 0033) is an optional free-form key/value store, also populated by
 * LeadsImportDefinition::persistRow() for imported rows.
 *
 * Lead status is derived from assignment/opportunity state and is not accepted
 * in the write contract.
 */
final readonly class CreateLeadData
{
    /**
     * @param  array<string, string>|null  $extraFields
     */
    public function __construct(
        public int $registryId,
        public int $campaignId,
        public ?int $operationalSiteId,
        public ?int $sourceId,
        public ?int $operatorId,
        public ?string $notes,
        public ?array $extraFields = null,
    ) {}

    /**
     * Build from the validated StoreLeadRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            registryId: (int) $data['registry_id'],
            campaignId: (int) $data['campaign_id'],
            operationalSiteId: isset($data['operational_site_id']) ? (int) $data['operational_site_id'] : null,
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            operatorId: isset($data['operator_id']) ? (int) $data['operator_id'] : null,
            notes: $data['notes'] ?? null,
            extraFields: $data['extra_fields'] ?? null,
        );
    }

    /**
     * The lead attributes for a mass-assignment create (framework array
     * boundary).
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'registry_id' => $this->registryId,
            'campaign_id' => $this->campaignId,
            'operational_site_id' => $this->operationalSiteId,
            'source_id' => $this->sourceId,
            'operator_id' => $this->operatorId,
            'notes' => $this->notes,
            'extra_fields' => $this->extraFields,
        ];
    }
}
