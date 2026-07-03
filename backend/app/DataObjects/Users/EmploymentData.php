<?php

namespace App\DataObjects\Users;

use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;

/**
 * The nested `employment` object submitted alongside a user write (spec
 * 0015): Profile / Contractual relationship / Contractual data.
 *
 * Tri-state wire semantics at the request boundary (ValidatesEmployment::
 * toEmployment()): the KEY absent yields a null DTO (leave the row
 * untouched); an explicit `employment: null` yields `EmploymentData::delete()`
 * (remove the row); a present object yields an upsert instance. Both create
 * and update funnel through the same EmploymentWriter — on create there is
 * never an existing row, so a `delete()` instance is a harmless no-op,
 * matching "absent or null => no row" for POST.
 */
final readonly class EmploymentData
{
    public function __construct(
        public bool $delete = false,
        public bool $isManager = false,
        public ?string $jobDescription = null,
        public ?int $reportsToId = null,
        public ?int $businessFunctionId = null,
        public ?RelationshipTypeEnum $relationshipType = null,
        public ?int $companyId = null,
        public ?int $operationalSiteId = null,
        public ?QualificationTypeEnum $qualificationType = null,
        public ?string $hiredAt = null,
        public ?string $terminatedAt = null,
        public ?int $standardDailyMinutes = null,
        public ?int $breakDailyMinutes = null,
    ) {}

    /**
     * The intent to remove the employment row (explicit `employment: null`).
     */
    public static function delete(): self
    {
        return new self(delete: true);
    }

    /**
     * The row attributes for a mass-assignment upsert (framework array
     * boundary). Never called when $delete is true.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'is_manager' => $this->isManager,
            'job_description' => $this->jobDescription,
            'reports_to_id' => $this->reportsToId,
            'business_function_id' => $this->businessFunctionId,
            'relationship_type' => $this->relationshipType,
            'company_id' => $this->companyId,
            'operational_site_id' => $this->operationalSiteId,
            'qualification_type' => $this->qualificationType,
            'hired_at' => $this->hiredAt,
            'terminated_at' => $this->terminatedAt,
            'standard_daily_minutes' => $this->standardDailyMinutes,
            'break_daily_minutes' => $this->breakDailyMinutes,
        ];
    }
}
