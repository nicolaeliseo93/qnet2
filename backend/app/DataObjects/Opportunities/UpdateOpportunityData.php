<?php

declare(strict_types=1);

namespace App\DataObjects\Opportunities;

/**
 * Validated payload for a partial (PATCH) opportunity update
 * (PUT/PATCH /api/opportunities/{opportunity}, spec 0040). Every scalar is a
 * legitimately nullable VALUE, so the `*Submitted` flags carry the "was this
 * key actually present" distinction a plain property cannot express
 * (mirrors UpdateLeadData). `managerSlots` follows CreateRegistryData/
 * UpdateRegistryData's simpler convention instead: null means "not
 * submitted, leave untouched", an array (including empty) is an
 * authoritative sync.
 *
 * `leadId` is deliberately ABSENT: `lead_id` is `prohibited` on update
 * (BR-2, immutable), so it never reaches this DTO. Every BR-2-locked field
 * that IS submitted has already been validated (UpdateOpportunityRequest) to
 * either match its current derived value or be absent — no extra
 * enforcement is needed here.
 */
final readonly class UpdateOpportunityData
{
    /**
     * @param  array<int, int|null>|null  $managerSlots
     */
    public function __construct(
        public ?string $name = null,
        public bool $nameSubmitted = false,
        public ?int $registryId = null,
        public bool $registryIdSubmitted = false,
        public ?int $companyId = null,
        public bool $companyIdSubmitted = false,
        public ?int $companySiteId = null,
        public bool $companySiteIdSubmitted = false,
        public ?int $operationalSiteId = null,
        public bool $operationalSiteIdSubmitted = false,
        public ?int $businessFunctionId = null,
        public bool $businessFunctionIdSubmitted = false,
        public ?int $referentId = null,
        public bool $referentIdSubmitted = false,
        public ?int $commercialId = null,
        public bool $commercialIdSubmitted = false,
        public ?int $reporterId = null,
        public bool $reporterIdSubmitted = false,
        public ?int $supervisorId = null,
        public bool $supervisorIdSubmitted = false,
        public ?int $sourceId = null,
        public bool $sourceIdSubmitted = false,
        public ?int $productCategoryId = null,
        public bool $productCategoryIdSubmitted = false,
        public ?array $managerSlots = null,
        public ?string $startDate = null,
        public bool $startDateSubmitted = false,
        public ?float $estimatedValue = null,
        public bool $estimatedValueSubmitted = false,
        public ?string $expectedCloseDate = null,
        public bool $expectedCloseDateSubmitted = false,
        public ?int $successProbability = null,
        public bool $successProbabilitySubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateOpportunityRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            nameSubmitted: array_key_exists('name', $data),
            registryId: self::nullableInt($data, 'registry_id'),
            registryIdSubmitted: array_key_exists('registry_id', $data),
            companyId: self::nullableInt($data, 'company_id'),
            companyIdSubmitted: array_key_exists('company_id', $data),
            companySiteId: self::nullableInt($data, 'company_site_id'),
            companySiteIdSubmitted: array_key_exists('company_site_id', $data),
            operationalSiteId: self::nullableInt($data, 'operational_site_id'),
            operationalSiteIdSubmitted: array_key_exists('operational_site_id', $data),
            businessFunctionId: self::nullableInt($data, 'business_function_id'),
            businessFunctionIdSubmitted: array_key_exists('business_function_id', $data),
            referentId: self::nullableInt($data, 'referent_id'),
            referentIdSubmitted: array_key_exists('referent_id', $data),
            commercialId: self::nullableInt($data, 'commercial_id'),
            commercialIdSubmitted: array_key_exists('commercial_id', $data),
            reporterId: self::nullableInt($data, 'reporter_id'),
            reporterIdSubmitted: array_key_exists('reporter_id', $data),
            supervisorId: self::nullableInt($data, 'supervisor_id'),
            supervisorIdSubmitted: array_key_exists('supervisor_id', $data),
            sourceId: self::nullableInt($data, 'source_id'),
            sourceIdSubmitted: array_key_exists('source_id', $data),
            productCategoryId: self::nullableInt($data, 'product_category_id'),
            productCategoryIdSubmitted: array_key_exists('product_category_id', $data),
            managerSlots: array_key_exists('manager_slots', $data)
                ? array_map(static fn ($id): ?int => $id === null ? null : (int) $id, $data['manager_slots'])
                : null,
            startDate: array_key_exists('start_date', $data) ? $data['start_date'] : null,
            startDateSubmitted: array_key_exists('start_date', $data),
            estimatedValue: array_key_exists('estimated_value', $data) && $data['estimated_value'] !== null ? (float) $data['estimated_value'] : null,
            estimatedValueSubmitted: array_key_exists('estimated_value', $data),
            expectedCloseDate: array_key_exists('expected_close_date', $data) ? $data['expected_close_date'] : null,
            expectedCloseDateSubmitted: array_key_exists('expected_close_date', $data),
            successProbability: self::nullableInt($data, 'success_probability'),
            successProbabilitySubmitted: array_key_exists('success_probability', $data),
        );
    }

    public function hasManagerSlots(): bool
    {
        return $this->managerSlots !== null;
    }

    /**
     * Only the attributes the client actually submitted, ready for a
     * partial mass-assignment update. `managerSlots` is never included: it
     * is synced separately by OpportunityService.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->nameSubmitted) {
            $attributes['name'] = $this->name;
        }

        if ($this->registryIdSubmitted) {
            $attributes['registry_id'] = $this->registryId;
        }

        if ($this->companyIdSubmitted) {
            $attributes['company_id'] = $this->companyId;
        }

        if ($this->companySiteIdSubmitted) {
            $attributes['company_site_id'] = $this->companySiteId;
        }

        if ($this->operationalSiteIdSubmitted) {
            $attributes['operational_site_id'] = $this->operationalSiteId;
        }

        if ($this->businessFunctionIdSubmitted) {
            $attributes['business_function_id'] = $this->businessFunctionId;
        }

        if ($this->referentIdSubmitted) {
            $attributes['referent_id'] = $this->referentId;
        }

        if ($this->commercialIdSubmitted) {
            $attributes['commercial_id'] = $this->commercialId;
        }

        if ($this->reporterIdSubmitted) {
            $attributes['reporter_id'] = $this->reporterId;
        }

        if ($this->supervisorIdSubmitted) {
            $attributes['supervisor_id'] = $this->supervisorId;
        }

        if ($this->sourceIdSubmitted) {
            $attributes['source_id'] = $this->sourceId;
        }

        if ($this->productCategoryIdSubmitted) {
            $attributes['product_category_id'] = $this->productCategoryId;
        }

        if ($this->startDateSubmitted) {
            $attributes['start_date'] = $this->startDate;
        }

        if ($this->estimatedValueSubmitted) {
            $attributes['estimated_value'] = $this->estimatedValue;
        }

        if ($this->expectedCloseDateSubmitted) {
            $attributes['expected_close_date'] = $this->expectedCloseDate;
        }

        if ($this->successProbabilitySubmitted) {
            $attributes['success_probability'] = $this->successProbability;
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
