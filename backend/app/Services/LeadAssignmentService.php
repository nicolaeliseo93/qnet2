<?php

namespace App\Services;

use App\Enums\LeadAssignmentMode;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for POST /api/leads/assign-operators (spec 0048): bulk-
 * assign a Sede and an Operatore to many REAL leads at once. Distinct from
 * LeadService (plain per-record CRUD): this is a bulk, cross-record write,
 * kept in its own Service (SRP). Distribution for `mode=balanced` is
 * delegated to LeadOperatorDistributor — the SAME algorithm ImportService::
 * bulkAssign uses for staged import rows.
 */
class LeadAssignmentService
{
    public function __construct(private readonly LeadOperatorDistributor $distributor) {}

    /**
     * Every lead in $leadIds always receives $operationalSiteId. In
     * `single` mode every lead also receives $operatorId; in `balanced`
     * mode operators are assigned per br-balanced (422 when the Sede has no
     * operators — see assignBalanced()). Whole operation is one transaction.
     *
     * @param  array<int, int>  $leadIds
     * @return int the number of leads assigned
     */
    public function assignOperators(array $leadIds, int $operationalSiteId, LeadAssignmentMode $mode, ?int $operatorId): int
    {
        return DB::transaction(function () use ($leadIds, $operationalSiteId, $mode, $operatorId): int {
            // Step 1: every targeted lead gets the chosen Sede regardless of mode.
            Lead::query()->whereIn('id', $leadIds)->update(['operational_site_id' => $operationalSiteId]);

            // Step 2: apply the operator(s) per mode.
            return $mode === LeadAssignmentMode::Single
                ? $this->assignSingleOperator($leadIds, $operatorId)
                : $this->assignBalanced($leadIds, $operationalSiteId);
        });
    }

    /**
     * @param  array<int, int>  $leadIds
     */
    private function assignSingleOperator(array $leadIds, ?int $operatorId): int
    {
        return Lead::query()->whereIn('id', $leadIds)->update(['operator_id' => $operatorId]);
    }

    /**
     * @param  array<int, int>  $leadIds
     */
    private function assignBalanced(array $leadIds, int $operationalSiteId): int
    {
        $operatorIds = $this->distributor->operatorIdsForSite($operationalSiteId);

        if ($operatorIds === []) {
            abort(422, 'The selected Sede has no operators to distribute leads to.');
        }

        $loads = $this->distributor->currentLoads($operatorIds);
        $orderedLeadIds = collect($leadIds)->sort()->values()->all();
        $assignments = $this->distributor->distribute($operatorIds, $loads, $orderedLeadIds);

        foreach ($this->distributor->groupByOperator($assignments) as $assignedOperatorId => $ids) {
            Lead::query()->whereIn('id', $ids)->update(['operator_id' => $assignedOperatorId]);
        }

        return count($assignments);
    }
}
