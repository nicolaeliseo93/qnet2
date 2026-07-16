<?php

declare(strict_types=1);

namespace App\DataObjects\Leads;

/**
 * Validated payload for creating a lead (POST /api/leads, spec 0024).
 *
 * Declared DTO (no "magic flying array") so the StoreLeadRequest ->
 * LeadService contract is explicit — see standards/architecture.md → Data
 * Transfer Objects. `referent_id`/`campaign_id` are mandatory (BR-1); no
 * `code` field exists for a Lead (D-3). `extra_fields` (spec 0033) is an
 * optional free-form key/value store, also populated by
 * LeadsImportDefinition::persistRow() for imported rows.
 *
 * spec 0039, D-3: `lead_status_id` is now NULLABLE — an omitted FK falls
 * back to the system_key='new' status, resolved server-side in
 * LeadService::create() (never here: a DTO stays pure data, no
 * App\Services\Statuses dependency).
 */
final readonly class CreateLeadData
{
    /**
     * @param  array<string, string>|null  $extraFields
     */
    public function __construct(
        public int $referentId,
        public int $campaignId,
        public ?int $operationalSiteId,
        public ?int $sourceId,
        public ?int $operatorId,
        public ?int $leadStatusId,
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
            referentId: (int) $data['referent_id'],
            campaignId: (int) $data['campaign_id'],
            operationalSiteId: isset($data['operational_site_id']) ? (int) $data['operational_site_id'] : null,
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            operatorId: isset($data['operator_id']) ? (int) $data['operator_id'] : null,
            leadStatusId: isset($data['lead_status_id']) ? (int) $data['lead_status_id'] : null,
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
            'referent_id' => $this->referentId,
            'campaign_id' => $this->campaignId,
            'operational_site_id' => $this->operationalSiteId,
            'source_id' => $this->sourceId,
            'operator_id' => $this->operatorId,
            'lead_status_id' => $this->leadStatusId,
            'notes' => $this->notes,
            'extra_fields' => $this->extraFields,
        ];
    }
}
