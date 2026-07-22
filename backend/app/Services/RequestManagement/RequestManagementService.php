<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\User;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\ApplicableAttributesResolver;
use App\RequestManagement\AttributeValueNormalizer;
use App\RequestManagement\AttributeValueValidator;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the request-management work panel (spec 0049): the
 * record IS an Opportunity (D-1), read/written through this dedicated
 * service rather than OpportunityService/OpportunityController — the
 * operative endpoints have their OWN authorization (`request-management.*`)
 * and their own write rules (D-4/D-5).
 *
 * `loadWorkPanel()`/`updateWork()` both return the SAME shape —
 * {opportunity, applicable_attributes, workflow_statuses} — consumed directly
 * by RequestManagementResource, so show/update render identically (data
 * contract: "Response identica alla GET").
 *
 * Activity logging: `opportunity_workflow_status_id` and `attribute_values`
 * are BOTH deliberately excluded from `Opportunity::$fillable` (D-4/D-5 mass-
 * assignment guard), and `LogsModelActivity::getActivitylogOptions()` calls
 * `logFillable()` — Spatie's dirty-diff only ever inspects the model's
 * fillable attributes. A change to either column therefore never reaches the
 * automatic model-event log. `updateWork()` compensates with an EXPLICIT
 * `activity()` call carrying the same `attributes`/`old` property shape the
 * automatic log would have produced, so GET /api/activity-log/request-
 * management/{id} (reading the Opportunity's own activity rows, D-7) still
 * sees the operative change (AC-043).
 */
final class RequestManagementService
{
    /**
     * Relations the work panel needs, eager-loaded in one shot (N+1-free):
     * contacts hang off each side's PersonalData card (HasPersonalData ->
     * HasContacts), never directly off Registry/Referent.
     *
     * @var array<int, string>
     */
    private const array WORK_PANEL_RELATIONS = [
        'registry.personalData.contacts',
        'referent.personalData.contacts',
        'commercial',
        'opportunityStatus',
        'workflowStatus',
        'productLines.businessFunction',
        'productLines.productCategory',
        'managers',
    ];

    public function __construct(
        private readonly ApplicableAttributesResolver $attributesResolver,
        private readonly AttributeValueValidator $attributeValueValidator,
        private readonly AttributeValueNormalizer $attributeValueNormalizer,
        private readonly OpportunityWorkflowResolver $workflowResolver,
    ) {}

    /**
     * @return array{opportunity: Opportunity, applicable_attributes: Collection<int, ApplicableAttribute>, workflow_statuses: Collection<int, OpportunityWorkflowStatus>}
     */
    public function loadWorkPanel(Opportunity $opportunity): array
    {
        $opportunity->loadMissing(self::WORK_PANEL_RELATIONS);

        return [
            'opportunity' => $opportunity,
            'applicable_attributes' => $this->attributesResolver->resolve($opportunity),
            'workflow_statuses' => $this->resolveWorkflowStatuses($opportunity),
        ];
    }

    /**
     * Applies the sparse PATCH payload (spec 0049 data_contract: only the
     * submitted keys change) and returns the SAME work-panel shape as
     * loadWorkPanel(), post-save.
     *
     * @param  array{opportunity_workflow_status_id?: int|null, attribute_values?: array<string, mixed>}  $data
     * @return array{opportunity: Opportunity, applicable_attributes: Collection<int, ApplicableAttribute>, workflow_statuses: Collection<int, OpportunityWorkflowStatus>}
     */
    public function updateWork(Opportunity $opportunity, User $actor, array $data): array
    {
        return DB::transaction(function () use ($opportunity, $actor, $data): array {
            $changed = [];
            $old = [];

            // Step 1: working-state advance — membership in the resolved set
            // already enforced request-side (ValidatesWorkflowStatus).
            if (array_key_exists('opportunity_workflow_status_id', $data) && $data['opportunity_workflow_status_id'] !== null) {
                $this->applyWorkflowStatus($opportunity, (int) $data['opportunity_workflow_status_id'], $changed, $old);
            }

            // Step 2: dynamic field values — validate against the CURRENT
            // applicable set (AttributeValueValidator, keyed
            // attribute_values.<code> on failure), then merge into the
            // existing map (sparse: unset codes keep their persisted value).
            if (array_key_exists('attribute_values', $data)) {
                $this->applyAttributeValues($opportunity, (array) $data['attribute_values'], $changed, $old);
            }

            $opportunity->save();

            // Step 3: explicit activity entry (see class docblock).
            $this->logOperationalChange($opportunity, $actor, $changed, $old);

            return $this->loadWorkPanel($opportunity);
        });
    }

    /**
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    private function applyWorkflowStatus(Opportunity $opportunity, int $newStatusId, array &$changed, array &$old): void
    {
        $currentStatusId = $opportunity->opportunity_workflow_status_id;

        if ($currentStatusId === $newStatusId) {
            return;
        }

        $old['opportunity_workflow_status_id'] = $currentStatusId;
        $opportunity->opportunity_workflow_status_id = $newStatusId;
        $changed['opportunity_workflow_status_id'] = $newStatusId;
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException
     */
    private function applyAttributeValues(Opportunity $opportunity, array $submitted, array &$changed, array &$old): void
    {
        $applicable = $this->attributesResolver->resolve($opportunity);
        $validated = $this->attributeValueValidator->validate($applicable, $submitted);
        $normalized = $this->attributeValueNormalizer->normalize($applicable, $validated);

        $current = $opportunity->attribute_values ?? [];
        $merged = array_merge($current, $normalized);

        if ($merged === $current) {
            return;
        }

        $old['attribute_values'] = $current;
        // `attribute_values` is NOT in Opportunity::$fillable (D-4 mass-
        // assignment guard): forceFill is the deliberate, single write path.
        $opportunity->forceFill(['attribute_values' => $merged]);
        $changed['attribute_values'] = $merged;
    }

    /**
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    private function logOperationalChange(Opportunity $opportunity, User $actor, array $changed, array $old): void
    {
        if ($changed === []) {
            return;
        }

        activity($opportunity->getTable())
            ->performedOn($opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management work update');
    }

    /**
     * @return Collection<int, OpportunityWorkflowStatus>
     */
    private function resolveWorkflowStatuses(Opportunity $opportunity): Collection
    {
        return $this->workflowResolver->statusesFor($this->workflowResolver->resolve($opportunity));
    }
}
