<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Opportunities\UpdateOpportunityData;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `opportunities` resource (spec 0040): create/
 * update/delete plus the BR-1 lead-derivation on create. `managerSlots`
 * sync mirrors RegistryService::syncPivots/managerSyncMap verbatim (the
 * pivot shape is identical, `opportunity_user` mirroring `registry_user`).
 *
 * `opportunity_status_id` (spec 0043, D-3) is mandatory at the FormRequest
 * layer, so the SystemStatusGuard fallback below only ever fires for a
 * caller that bypasses validation (e.g. a seeder passing null) — defense in
 * depth, mirroring the Lead/Project 'new'-status fallback precedent.
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
        'referent',
        'commercial',
        'reporter',
        'supervisor',
        'source',
        'opportunityStatus',
        'productLines.businessFunction',
        'productLines.productCategory',
        'managers',
        'lead.registry',
        'lead.operationalSite.addresses.city',
        'lead.source',
        'lead.campaign.businessFunction',
        'lead.campaign.productCategory',
        'lead.campaign.project.businessFunction',
        'lead.campaign.project.productCategory',
        // spec 0047 (AC-003): Regione + resolved working-state row.
        'state',
        'workflowStatus',
    ];

    public function __construct(
        private readonly LeadOpportunityDefaultsResolver $defaultsResolver,
        private readonly SystemStatusGuard $systemStatusGuard,
        private readonly OpportunityWorkflowResolver $workflowResolver,
    ) {}

    public function loadDetail(Opportunity $opportunity): Opportunity
    {
        return $opportunity->load(self::DETAIL_RELATIONS);
    }

    /**
     * Create a new opportunity. When `lead_id` is submitted, the 2 BR-1-
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

            if ($attributes['opportunity_status_id'] === null) {
                $attributes['opportunity_status_id'] = $this->systemStatusGuard->resolveNewStatusId(OpportunityStatus::class);
            }

            $opportunity = Opportunity::create($attributes);

            if ($data->hasManagerSlots()) {
                $opportunity->managers()->sync($this->managerSyncMap($data->managerSlots));
            }

            if ($data->hasProductLines()) {
                $this->syncProductLines($opportunity, $data->productLines);
            }

            // spec 0047 (AC-015/017): an explicit, already-validated override
            // wins; otherwise the resolver derives the 'open' row of the
            // resolved set — product lines are already synced above, so
            // business_function_id/product_category_id criteria see their
            // final values.
            $this->resolveWorkflowStatus($opportunity, $data->workflowStatusId);

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

            if ($data->hasProductLines()) {
                $this->syncProductLines($opportunity, $data->productLines);
            }

            // spec 0047 (AC-016/017): re-resolve after any change to the
            // resolving criteria (source_id/state_id/product lines) unless
            // the client explicitly (and already-validated) chose a status.
            $this->resolveWorkflowStatus(
                $opportunity,
                $data->workflowStatusIdSubmitted ? $data->workflowStatusId : null,
            );
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
     * The single write-side entry point for `opportunity_workflow_status_id`
     * (spec 0047): an explicit, non-null $submittedStatusId (already
     * validated by ValidatesWorkflowStatus to belong to the resolved set) is
     * written verbatim; otherwise OpportunityWorkflowResolver derives and
     * persists it — the SAME resolver Lane A's delete-reassign flow calls,
     * never duplicated here.
     */
    private function resolveWorkflowStatus(Opportunity $opportunity, ?int $submittedStatusId): void
    {
        if ($submittedStatusId !== null) {
            $opportunity->opportunity_workflow_status_id = $submittedStatusId;
            $opportunity->save();

            return;
        }

        $this->workflowResolver->resolveAndAssign($opportunity);
    }

    /**
     * Overwrite $attributes' 2 BR-1-derivable keys with the linked lead's
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

    /**
     * Full-replace sync of $opportunity's product lines (spec 0040 amendment
     * rev.3): delete-all + insert, idempotent within the surrounding
     * transaction — StoreOpportunityRequest/UpdateOpportunityRequest already
     * rejected duplicate pairs and a mismatched business-function/category
     * pairing (withValidator), so every row here is already valid.
     *
     * @param  array<int, array{business_function_id: int, product_category_id: int}>  $lines
     */
    private function syncProductLines(Opportunity $opportunity, array $lines): void
    {
        $opportunity->productLines()->delete();

        foreach ($lines as $line) {
            $opportunity->productLines()->create($line);
        }
    }
}
