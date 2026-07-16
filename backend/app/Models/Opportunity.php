<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Opportunity entity (spec 0040): a commercial deal against an Anagrafica
 * (`registry`), created manually or generated from a `Lead` (`lead_id`,
 * nullable, UNIQUE — D-2: at most one opportunity per lead). `name`/
 * `registry_id`/`company_id`/`company_site_id`/`operational_site_id` are
 * mandatory (D-4, amendment rev.1 A-2); every other relation is optional.
 * `commercial`/`reporter` mirror Registry's own referent fields; `referent`
 * is BR-1-derivable from the linked lead, like `source`/`operational_site`/
 * `business_function`/`product_category` — `company`/`company_site` are NOT
 * derivable (no lead/campaign chain to either), always freely required. No
 * `code` (D-3: no opportunity status/sequence in this iteration).
 */
#[Fillable([
    'name',
    'registry_id',
    'company_id',
    'company_site_id',
    'operational_site_id',
    'business_function_id',
    'referent_id',
    'commercial_id',
    'reporter_id',
    'supervisor_id',
    'source_id',
    'product_category_id',
    'lead_id',
    'start_date',
    'estimated_value',
    'expected_close_date',
    'success_probability',
])]
class Opportunity extends BaseModel
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expected_close_date' => 'date',
            'estimated_value' => 'decimal:2',
            'success_probability' => 'integer',
        ];
    }

    public function registry(): BelongsTo
    {
        return $this->belongsTo(Registry::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function companySite(): BelongsTo
    {
        return $this->belongsTo(CompanySite::class);
    }

    public function operationalSite(): BelongsTo
    {
        return $this->belongsTo(OperationalSite::class);
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    /**
     * The contact person driving the deal (BR-1-derivable from the linked
     * lead's own referent).
     */
    public function referent(): BelongsTo
    {
        return $this->belongsTo(Referent::class);
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(Referent::class, 'commercial_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Referent::class, 'reporter_id');
    }

    /**
     * The internal user supervising the deal ("Supervisore") — an employee,
     * not an external referent, mirroring Registry::supervisor().
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * The lead this opportunity was generated from, if any (BR-1/D-2:
     * nullable, unique — read-only server-side derivation lock, see
     * OpportunityService/LeadOpportunityDefaultsResolver).
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The internal users managing this opportunity ("Gestori Account", max
     * 4 — validation-layer only, see StoreOpportunityRequest), mirroring
     * Registry::managers() verbatim (spec 0040, "ranking inherited by future
     * modules like Opportunities" per the 2026_07_13_130000 docblock).
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'opportunity_user')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
