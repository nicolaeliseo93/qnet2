<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;

/**
 * Writes the GA2 "Operatore" slot of an opportunity: the single
 * `opportunity_user` row at pivot position
 * Opportunity::OPERATOR_MANAGER_POSITION. Only THAT slot is touched — the
 * other manager positions belong to the opportunities form and must survive a
 * write from this module, so `sync()` (which detaches everything absent from
 * its map) is deliberately not used here.
 *
 * A user already attached at another position is MOVED to the operator slot
 * rather than duplicated: the pivot's identity is (opportunity, user), one
 * person cannot hold two slots.
 *
 * Extracted from RequestManagementService (user directive 2026-07-23) so the
 * work panel's per-record write and the bulk assignment
 * (RequestAssignmentService) share ONE implementation of the rule instead of
 * two that can drift.
 */
final class RequestOperatorWriter
{
    /**
     * Reports the transition into $changed/$old (nothing when the slot
     * already holds $operatorId) — the pivot is not a fillable attribute, so
     * the automatic model-event log never sees it and the CALLER owns the
     * explicit activity entry.
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function apply(Opportunity $opportunity, ?int $operatorId, array &$changed, array &$old): void
    {
        $current = $opportunity->operatorManager()?->id;

        if ($current === $operatorId) {
            return;
        }

        if ($current !== null) {
            $opportunity->managers()->detach($current);
        }

        if ($operatorId !== null) {
            // detach-then-attach also covers the "was GA1, becomes GA2" move:
            // the person keeps exactly one row, now at the operator position.
            $opportunity->managers()->detach($operatorId);
            $opportunity->managers()->attach($operatorId, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);
        }

        $opportunity->unsetRelation('managers');

        $old['operator_id'] = $current;
        $changed['operator_id'] = $operatorId;
    }
}
