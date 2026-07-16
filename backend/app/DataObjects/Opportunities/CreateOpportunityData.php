<?php

declare(strict_types=1);

namespace App\DataObjects\Opportunities;

/**
 * Validated payload for creating an opportunity (POST /api/opportunities,
 * spec 0040). `name`/`registryId` are the only mandatory scalars (D-4);
 * every other relation is optional. `managerSlots` is ORDERED and gap-aware,
 * mirroring CreateRegistryData verbatim: index+1 is the manager's static
 * "G.A. n" position, a null entry an intentionally empty slot — synced by
 * OpportunityService post-create via `->sync()`, so it is NOT a mass-
 * assignable column and stays out of attributes().
 *
 * With `leadId` set, OpportunityService overwrites the BR-1-derivable
 * attributes with LeadOpportunityDefaultsResolver's values (the FormRequest
 * already rejected a conflicting submission as `prohibited`).
 */
final readonly class CreateOpportunityData
{
    /**
     * @param  array<int, int|null>|null  $managerSlots
     */
    public function __construct(
        public string $name,
        public ?int $registryId,
        public ?int $companyId,
        public ?int $companySiteId,
        public ?int $operationalSiteId,
        public ?int $businessFunctionId,
        public ?int $referentId,
        public ?int $commercialId,
        public ?int $reporterId,
        public ?int $supervisorId,
        public ?int $sourceId,
        public ?int $productCategoryId,
        public ?int $leadId,
        public ?array $managerSlots,
        public ?string $startDate,
        public ?float $estimatedValue,
        public ?string $expectedCloseDate,
        public ?int $successProbability,
    ) {}

    /**
     * Build from the validated StoreOpportunityRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            registryId: isset($data['registry_id']) ? (int) $data['registry_id'] : null,
            companyId: isset($data['company_id']) ? (int) $data['company_id'] : null,
            companySiteId: isset($data['company_site_id']) ? (int) $data['company_site_id'] : null,
            operationalSiteId: isset($data['operational_site_id']) ? (int) $data['operational_site_id'] : null,
            businessFunctionId: isset($data['business_function_id']) ? (int) $data['business_function_id'] : null,
            referentId: isset($data['referent_id']) ? (int) $data['referent_id'] : null,
            commercialId: isset($data['commercial_id']) ? (int) $data['commercial_id'] : null,
            reporterId: isset($data['reporter_id']) ? (int) $data['reporter_id'] : null,
            supervisorId: isset($data['supervisor_id']) ? (int) $data['supervisor_id'] : null,
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            productCategoryId: isset($data['product_category_id']) ? (int) $data['product_category_id'] : null,
            leadId: isset($data['lead_id']) ? (int) $data['lead_id'] : null,
            managerSlots: array_key_exists('manager_slots', $data)
                ? array_map(static fn ($id): ?int => $id === null ? null : (int) $id, $data['manager_slots'])
                : null,
            startDate: $data['start_date'] ?? null,
            estimatedValue: isset($data['estimated_value']) ? (float) $data['estimated_value'] : null,
            expectedCloseDate: $data['expected_close_date'] ?? null,
            successProbability: isset($data['success_probability']) ? (int) $data['success_probability'] : null,
        );
    }

    public function hasManagerSlots(): bool
    {
        return $this->managerSlots !== null;
    }

    /**
     * The opportunity's own scalar attributes for a mass-assignment create
     * (framework array boundary). `managerSlots` is NOT included: it is a
     * to-many reference synced separately by OpportunityService.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'registry_id' => $this->registryId,
            'company_id' => $this->companyId,
            'company_site_id' => $this->companySiteId,
            'operational_site_id' => $this->operationalSiteId,
            'business_function_id' => $this->businessFunctionId,
            'referent_id' => $this->referentId,
            'commercial_id' => $this->commercialId,
            'reporter_id' => $this->reporterId,
            'supervisor_id' => $this->supervisorId,
            'source_id' => $this->sourceId,
            'product_category_id' => $this->productCategoryId,
            'lead_id' => $this->leadId,
            'start_date' => $this->startDate,
            'estimated_value' => $this->estimatedValue,
            'expected_close_date' => $this->expectedCloseDate,
            'success_probability' => $this->successProbability,
        ];
    }
}
