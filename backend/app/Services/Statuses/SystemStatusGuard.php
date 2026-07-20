<?php

namespace App\Services\Statuses;

use App\Enums\StatusSystemKey;
use App\Models\LeadStatus;
use App\Models\OpportunityStatus;
use App\Models\PipelineStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The system-status protection rules (spec 0039, D-2; extended to
 * opportunity_statuses by spec 0043), shared verbatim by every status
 * configurator (pipeline_statuses, lead_statuses, opportunity_statuses):
 * every mandatory row cannot be deleted, and only its `name`/`color` may
 * ever change — `group` (pivot, App\Enums\StatusGroup) is fixed at migration
 * time and never reassigned. Mirrors the precedent guard for a single
 * protected system row, RoleService::guardSystemRoleMutation (the
 * `super-admin` role).
 */
class SystemStatusGuard
{
    /**
     * @throws HttpException 422
     */
    public function assertDeletable(PipelineStatus|LeadStatus|OpportunityStatus $status): void
    {
        if (! $status->isSystem()) {
            return;
        }

        abort(422, "The '{$status->name}' status is a system status and cannot be deleted.");
    }

    /**
     * @param  array<string, mixed>  $submittedAttributes  the attributes the
     *                                                     client actually submitted (UpdatePipelineStatusData/
     *                                                     UpdateLeadStatusData/UpdateOpportunityStatusData::submittedAttributes()) —
     *                                                     checked by KEY, so an update that never touches `group` (e.g.
     *                                                     name/color only) is always allowed on a system row.
     *
     * @throws HttpException 422
     */
    public function assertUpdatable(PipelineStatus|LeadStatus|OpportunityStatus $status, array $submittedAttributes): void
    {
        if (! $status->isSystem()) {
            return;
        }

        if (array_key_exists('group', $submittedAttributes)) {
            abort(422, 'System statuses accept only name and color changes.');
        }
    }

    /**
     * The id of the mandatory "Nuovo" system row for $modelClass — the
     * fallback assigned when a Lead/Project/(standalone) Campaign/Opportunity
     * is created without an explicit status FK (spec 0039, D-3; spec 0043).
     * Resolved by `system_key`, never by name (D-3: "query per system_key,
     * non per nome").
     *
     * @param  class-string<PipelineStatus>|class-string<LeadStatus>|class-string<OpportunityStatus>  $modelClass
     *
     * @throws HttpException 500 if the
     *                       mandatory row is somehow missing (should never happen post-migration,
     *                       defense in depth)
     */
    public function resolveNewStatusId(string $modelClass): int
    {
        $id = $modelClass::query()->where('system_key', StatusSystemKey::New->value)->value('id');

        if ($id === null) {
            abort(500, "The system 'new' status is missing for {$modelClass}.");
        }

        return (int) $id;
    }
}
