<?php

declare(strict_types=1);

namespace App\DataObjects\Leads;

/**
 * Validated payload for a partial (PATCH) lead update
 * (PUT/PATCH /api/leads/{lead}, spec 0024).
 *
 * Declared DTO (no "magic flying array") so the UpdateLeadRequest ->
 * LeadService contract is explicit, mirroring UpdateCampaignData: every field
 * is a legitimately nullable/omittable VALUE, so the `*Submitted` flags carry
 * the "was this key actually present" distinction a plain property cannot
 * express (AC-013: a PATCH with only `notes` must leave the 6 FKs untouched).
 * `extra_fields` (spec 0033) follows the same submitted-flag convention.
 */
final readonly class UpdateLeadData
{
    /**
     * @param  array<string, string>|null  $extraFields
     */
    public function __construct(
        public ?int $referentId = null,
        public bool $referentIdSubmitted = false,
        public ?int $campaignId = null,
        public bool $campaignIdSubmitted = false,
        public ?int $operationalSiteId = null,
        public bool $operationalSiteIdSubmitted = false,
        public ?int $sourceId = null,
        public bool $sourceIdSubmitted = false,
        public ?int $operatorId = null,
        public bool $operatorIdSubmitted = false,
        public ?int $leadStatusId = null,
        public bool $leadStatusIdSubmitted = false,
        public ?string $notes = null,
        public bool $notesSubmitted = false,
        public ?array $extraFields = null,
        public bool $extraFieldsSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateLeadRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            referentId: self::nullableInt($data, 'referent_id'),
            referentIdSubmitted: array_key_exists('referent_id', $data),
            campaignId: self::nullableInt($data, 'campaign_id'),
            campaignIdSubmitted: array_key_exists('campaign_id', $data),
            operationalSiteId: self::nullableInt($data, 'operational_site_id'),
            operationalSiteIdSubmitted: array_key_exists('operational_site_id', $data),
            sourceId: self::nullableInt($data, 'source_id'),
            sourceIdSubmitted: array_key_exists('source_id', $data),
            operatorId: self::nullableInt($data, 'operator_id'),
            operatorIdSubmitted: array_key_exists('operator_id', $data),
            leadStatusId: self::nullableInt($data, 'lead_status_id'),
            leadStatusIdSubmitted: array_key_exists('lead_status_id', $data),
            notes: array_key_exists('notes', $data) ? $data['notes'] : null,
            notesSubmitted: array_key_exists('notes', $data),
            extraFields: array_key_exists('extra_fields', $data) ? $data['extra_fields'] : null,
            extraFieldsSubmitted: array_key_exists('extra_fields', $data),
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

        if ($this->referentIdSubmitted) {
            $attributes['referent_id'] = $this->referentId;
        }

        if ($this->campaignIdSubmitted) {
            $attributes['campaign_id'] = $this->campaignId;
        }

        if ($this->operationalSiteIdSubmitted) {
            $attributes['operational_site_id'] = $this->operationalSiteId;
        }

        if ($this->sourceIdSubmitted) {
            $attributes['source_id'] = $this->sourceId;
        }

        if ($this->operatorIdSubmitted) {
            $attributes['operator_id'] = $this->operatorId;
        }

        if ($this->leadStatusIdSubmitted) {
            $attributes['lead_status_id'] = $this->leadStatusId;
        }

        if ($this->notesSubmitted) {
            $attributes['notes'] = $this->notes;
        }

        if ($this->extraFieldsSubmitted) {
            $attributes['extra_fields'] = $this->extraFields;
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableInt(array $data, string $key): ?int
    {
        return array_key_exists($key, $data) && $data[$key] !== null ? (int) $data[$key] : null;
    }
}
