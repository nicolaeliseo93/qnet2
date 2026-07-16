<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Opportunities\UpdateOpportunityData;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `opportunities` resource (spec 0040): create/
 * update/delete plus the BR-1 lead-derivation on create. `managerSlots`
 * sync mirrors RegistryService::syncPivots/managerSyncMap verbatim (the
 * pivot shape is identical, `opportunity_user` mirroring `registry_user`).
 */
class OpportunityService
{
    /**
     * Relations eager-loaded for the detail read tree (OpportunityResource),
     * so a single query never N+1s — including the linked lead's own chain
     * (LeadOpportunityDefaultsResolver's requirements, for `locked_fields`
     * and the `lead.label` summary).
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'registry',
        'company',
        'companySite',
        'operationalSite.addresses.city',
        'businessFunction',
        'referent',
        'commercial',
        'reporter',
        'supervisor',
        'source',
        'productCategory',
        'managers',
        'lead.referent',
        'lead.operationalSite.addresses.city',
        'lead.source',
        'lead.campaign.source',
        'lead.campaign.registry',
        'lead.campaign.businessFunction',
        'lead.campaign.productCategory',
        'lead.campaign.project.businessFunction',
        'lead.campaign.project.productCategory',
    ];

    public function __construct(private readonly LeadOpportunityDefaultsResolver $defaultsResolver) {}

    public function loadDetail(Opportunity $opportunity): Opportunity
    {
        return $opportunity->load(self::DETAIL_RELATIONS);
    }

    /**
     * Create a new opportunity. When `lead_id` is submitted, the 6 BR-1-
     * derivable attributes are overwritten with LeadOpportunityDefaultsResolver's
     * values (StoreOpportunityRequest already rejected a conflicting
     * submission as `prohibited`, so this only ever fills in fields the
     * client left absent).
     */
    public function create(CreateOpportunityData $data): Opportunity
    {
        $opportunity = DB::transaction(function () use ($data): Opportunity {
            $attributes = $data->attributes();

            if ($data->leadId !== null) {
                $attributes = $this->applyLeadDefaults($attributes, $data->leadId);
            }

            $opportunity = Opportunity::create($attributes);

            if ($data->hasManagerSlots()) {
                $opportunity->managers()->sync($this->managerSyncMap($data->managerSlots));
            }

            return $opportunity;
        });

        return $this->loadDetail($opportunity);
    }

    /**
     * Update an existing opportunity. Only keys present in $data are
     * touched (partial PATCH); a BR-2-locked field, if submitted, has
     * already been validated (UpdateOpportunityRequest) to match its
     * current derived value, so no extra enforcement runs here.
     */
    public function update(Opportunity $opportunity, UpdateOpportunityData $data): Opportunity
    {
        DB::transaction(function () use ($opportunity, $data): void {
            // Unconditional save: fire the model's saved event even when no
            // native attribute changed, so the HasCustomFields write pipeline
            // (spec 0021) persists a custom-fields-only edit.
            $opportunity->fill($data->submittedAttributes())->save();

            if ($data->hasManagerSlots()) {
                $opportunity->managers()->sync($this->managerSyncMap($data->managerSlots));
            }
        });

        return $this->loadDetail($opportunity);
    }

    /**
     * Delete the opportunity. The linked lead (if any) is left untouched
     * (D-5); the `opportunity_user` pivot rows cascade away via their own
     * cascadeOnDelete foreign keys (BR-3 explicitly excludes this pivot).
     */
    public function delete(Opportunity $opportunity): void
    {
        $opportunity->delete();
    }

    /**
     * Overwrite $attributes' 6 BR-1-derivable keys with the linked lead's
     * current defaults, for every field whose derivation is non-null.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyLeadDefaults(array $attributes, int $leadId): array
    {
        $lead = Lead::findOrFail($leadId);
        $defaults = $this->defaultsResolver->resolve($lead);

        foreach ($defaults->lockedFields as $field) {
            $attributes[$field] = $defaults->values[$field];
        }

        return $attributes;
    }

    /**
     * Turn the ordered, gap-aware manager slots into the pivot sync map
     * `[userId => ['position' => n]]` (mirrors RegistryService::managerSyncMap
     * verbatim — identical pivot shape).
     *
     * @param  array<int, int|null>  $slots
     * @return array<int, array{position: int}>
     */
    private function managerSyncMap(array $slots): array
    {
        $map = [];

        foreach (array_values($slots) as $index => $userId) {
            if ($userId !== null) {
                $map[$userId] = ['position' => $index + 1];
            }
        }

        return $map;
    }
}
