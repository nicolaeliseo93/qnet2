<?php

namespace App\Models;

use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\EmploymentProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's employment profile (spec 0015): Profile (manager flag, job
 * description, reports-to), Contractual relationship (function, type,
 * company, site, qualification, dates) and Contractual data (daily minutes).
 * One row per user (hasOne on User via HasEmployment).
 */
#[Fillable([
    'user_id',
    'is_manager',
    'job_description',
    'reports_to_id',
    'business_function_id',
    'relationship_type',
    'company_id',
    'operational_site_id',
    'qualification_type',
    'hired_at',
    'terminated_at',
    'standard_daily_minutes',
    'break_daily_minutes',
])]
class EmploymentProfile extends BaseModel
{
    /** @use HasFactory<EmploymentProfileFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_manager' => 'boolean',
            'relationship_type' => RelationshipTypeEnum::class,
            'qualification_type' => QualificationTypeEnum::class,
            'hired_at' => 'date:Y-m-d',
            'terminated_at' => 'date:Y-m-d',
            'standard_daily_minutes' => 'integer',
            'break_daily_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The manager this employee reports to, if any (self-referencing on User).
     */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reports_to_id');
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function operationalSite(): BelongsTo
    {
        return $this->belongsTo(OperationalSite::class);
    }
}
