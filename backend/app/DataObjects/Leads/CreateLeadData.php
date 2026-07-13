<?php

declare(strict_types=1);

namespace App\DataObjects\Leads;

/**
 * Validated payload for creating a lead (POST /api/leads, spec 0024).
 *
 * Declared DTO (no "magic flying array") so the StoreLeadRequest ->
 * LeadService contract is explicit — see standards/architecture.md → Data
 * Transfer Objects. `referent_id`/`campaign_id` are mandatory (BR-1); no
 * `code` field exists for a Lead (D-3).
 */
final readonly class CreateLeadData
{
    public function __construct(
        public int $referentId,
        public int $campaignId,
        public ?int $operationalSiteId,
        public ?int $sourceId,
        public ?int $operatorId,
        public ?string $notes,
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
            notes: $data['notes'] ?? null,
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
            'notes' => $this->notes,
        ];
    }
}
