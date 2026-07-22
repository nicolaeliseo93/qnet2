<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opportunity entity (spec 0040): a commercial deal against an Anagrafica
 * (`registry`), created manually or generated from a `Lead` (`lead_id`,
 * nullable, UNIQUE — D-2: at most one opportunity per lead). `name`/
 * `registry_id` are mandatory (D-4); every other relation is optional.
 * `commercial`/`reporter` mirror Registry's own referent fields; `referent`
 * is NOT derivable (spec 0041 D-3), a plain always-editable field; `source`
 * is BR-1-derivable from the linked lead. Amendment rev.3: the former single
 * `business_function_id`/`product_category_id` columns are REPLACED by
 * `productLines()`, a one-to-many collection (see OpportunityProductLine).
 * User directive 2026-07-17: `company_id`/`company_site_id`/
 * `operational_site_id` and their relations are REMOVED entirely.
 * `opportunity_status_id` (spec 0043, D-3): the mandatory working-state FK,
 * NOT NULL at schema level, defaulted server-side to the system 'new' status
 * when omitted (see OpportunityService).
 */
#[Fillable([
    'name',
    'registry_id',
    'referent_id',
    'commercial_id',
    'reporter_id',
    'supervisor_id',
    'source_id',
    'lead_id',
    'opportunity_status_id',
    'state_id',
    'start_date',
    'estimated_value',
    'expected_close_date',
    'success_probability',
])]
class Opportunity extends BaseModel
{
    /** @use HasFactory<OpportunityFactory> */
    use HasAttachments, HasFactory, LogsModelActivity;

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
            'attribute_values' => 'array',
        ];
    }

    public function registry(): BelongsTo
    {
        return $this->belongsTo(Registry::class);
    }

    /**
     * The contact person driving the deal. NOT derivable (spec 0041 D-3): a
     * plain, always-editable field scoped to the chosen registry (BR-4).
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

    /**
     * The Opportunity's working-state classification (spec 0043, D-3):
     * mandatory (NOT NULL at schema level), restrictOnDelete.
     */
    public function opportunityStatus(): BelongsTo
    {
        return $this->belongsTo(OpportunityStatus::class);
    }

    /**
     * The Regione (spec 0047, D1): inherited from the originating Lead at
     * conversion (LeadOpportunityDefaultsResolver) or editable on a
     * standalone Opportunity.
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class, 'state_id');
    }

    /**
     * The currently resolved working-state row (spec 0047 — the NEW workflow
     * dimension, distinct from `opportunityStatus()`/pipeline). Always
     * written by OpportunityWorkflowResolver, never directly
     * mass-assignable.
     */
    public function workflowStatus(): BelongsTo
    {
        return $this->belongsTo(OpportunityWorkflowStatus::class, 'opportunity_workflow_status_id');
    }

    /**
     * The funzione-aziendale + categoria-prodotto rows against this
     * opportunity (spec 0040, amendment rev.3): REPLACES the former single
     * `business_function_id`/`product_category_id` columns.
     *
     * @return HasMany<OpportunityProductLine, $this>
     */
    public function productLines(): HasMany
    {
        return $this->hasMany(OpportunityProductLine::class);
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
     * The Account Manager pivot `position` that designates the "Operatore"
     * (GA2) — the operative owner the request-management module (spec 0049)
     * uses both for the "my requests" scope and the "Operatore" column.
     */
    public const int OPERATOR_MANAGER_POSITION = 2;

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
