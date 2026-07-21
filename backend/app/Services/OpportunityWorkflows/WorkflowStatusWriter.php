<?php

declare(strict_types=1);

namespace App\Services\OpportunityWorkflows;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\OpportunityWorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * `opportunity_workflow_statuses` write-side, SCOPED per set (a workflow's
 * own `opportunity_workflow_id`, or the GLOBAL default set when null) —
 * modeled on App\Services\Statuses\SystemStatusGuard +
 * App\Services\Statuses\StatusOrderManager (spec 0039/0043), NOT reused
 * directly: those two are hardcoded to the PipelineStatus/OpportunityStatus
 * shape (a single global set, StatusSystemKey::New tail-anchoring), whereas
 * every OpportunityWorkflowStatus set independently pins its OWN system rows —
 * the initial 'open' row plus the two terminal closed-outcome rows
 * 'closed_won'/'closed_lost' (AC-004/AC-005).
 *
 * `opportunity_workflow_id`/`system_key` are DELIBERATELY absent from
 * OpportunityWorkflowStatus's #[Fillable] (never mass-assignable from a
 * request), so every write here that touches either goes through
 * forceFill()/forceCreate() — the request-payload fields (name/color/group)
 * remain on the normal, guarded fill() path.
 */
final class WorkflowStatusWriter
{
    private const int STEP = 10;

    /**
     * Creates a brand-new set (AC-004): the pinned 'open' row (sort_order 0),
     * every $customStatuses row in submission order (STEP apart), then the two
     * pinned terminal rows 'closed_won'/'closed_lost' (always last, in that
     * order). $openOverride/$closedWonOverride/$closedLostOverride seed the
     * pinned rows' name/color when the client named them up front; null falls
     * back to the default label.
     *
     * @param  array<int, array{name: string, color: ?string, group: string}>  $customStatuses
     * @param  array{name: string, color: ?string}|null  $openOverride
     * @param  array{name: string, color: ?string}|null  $closedWonOverride
     * @param  array{name: string, color: ?string}|null  $closedLostOverride
     */
    public function createWithCustoms(
        ?int $workflowId,
        array $customStatuses,
        ?array $openOverride = null,
        ?array $closedWonOverride = null,
        ?array $closedLostOverride = null,
    ): void {
        $this->forceCreateSystemRow($workflowId, WorkflowStatusSystemKey::Open, 0, $openOverride);

        $sortOrder = self::STEP;

        foreach ($customStatuses as $status) {
            $this->forceCreateCustomRow($workflowId, $status, $sortOrder);
            $sortOrder += self::STEP;
        }

        $closedOverrides = [
            WorkflowStatusSystemKey::ClosedWon->value => $closedWonOverride,
            WorkflowStatusSystemKey::ClosedLost->value => $closedLostOverride,
        ];

        foreach (WorkflowStatusSystemKey::closedKeys() as $key) {
            $this->forceCreateSystemRow($workflowId, $key, $sortOrder, $closedOverrides[$key->value]);
            $sortOrder += self::STEP;
        }
    }

    /**
     * Authoritative sync of $set's CUSTOM rows (id present = update, absent =
     * new; existing customs not included = deleted), resequencing sort_order
     * so 'open' stays first and the two 'closed_won'/'closed_lost' rows stay
     * last. A submitted row whose
     * `id` matches an existing SYSTEM row is routed to
     * assertMutableSystemRow() instead (name/color only, spec 0047 data
     * contract) and never counted as a custom / never deleted.
     *
     * @param  array<int, array{id: ?int, name: string, color: ?string, group: string}>  $statusRows
     */
    public function syncCustoms(?int $workflowId, array $statusRows): void
    {
        DB::transaction(function () use ($workflowId, $statusRows): void {
            $existing = OpportunityWorkflowStatus::query()
                ->where('opportunity_workflow_id', $workflowId)
                ->get()
                ->keyBy('id');

            [$systemRows, $customRows] = $this->partitionSubmitted($statusRows, $existing);

            $this->applySystemUpdates($systemRows, $existing);
            $orderedCustomIds = $this->applyCustomSync($workflowId, $customRows, $existing);
            $this->resequence($workflowId, $orderedCustomIds);
        });
    }

    /**
     * A system row accepts only a `name`/`color` change (spec 0047 data
     * contract): rejects, with a 422, any submission whose `group` differs
     * from what is currently persisted.
     *
     * @param  array{id: ?int, name: string, color: ?string, group: string}  $submitted
     */
    public function assertMutableSystemRow(OpportunityWorkflowStatus $status, array $submitted): void
    {
        if ($submitted['group'] !== $status->group->value) {
            abort(422, "The '{$status->name}' status is a system status: only name/color may change.");
        }
    }

    /**
     * @param  array{name: string, color: ?string}|null  $override  client-supplied name/color seed for this pinned row
     */
    private function forceCreateSystemRow(?int $workflowId, WorkflowStatusSystemKey $key, int $sortOrder, ?array $override = null): void
    {
        [$defaultName, $group] = match ($key) {
            WorkflowStatusSystemKey::Open => ['Aperta', WorkflowStatusGroup::Open],
            WorkflowStatusSystemKey::ClosedWon => ['Chiusa positiva', WorkflowStatusGroup::ClosedWon],
            WorkflowStatusSystemKey::ClosedLost => ['Chiusa negativa', WorkflowStatusGroup::ClosedLost],
        };

        OpportunityWorkflowStatus::query()->forceCreate([
            'opportunity_workflow_id' => $workflowId,
            'name' => $override['name'] ?? $defaultName,
            'color' => $override['color'] ?? null,
            'sort_order' => $sortOrder,
            'system_key' => $key->value,
            'group' => $group,
        ]);
    }

    /**
     * @param  array{name: string, color: ?string, group: string}  $status
     */
    private function forceCreateCustomRow(?int $workflowId, array $status, int $sortOrder): void
    {
        OpportunityWorkflowStatus::query()->forceCreate([
            'opportunity_workflow_id' => $workflowId,
            'name' => $status['name'],
            'color' => $status['color'],
            'sort_order' => $sortOrder,
            'system_key' => null,
            'group' => $status['group'],
        ]);
    }

    /**
     * Splits $statusRows into [systemRows, customRows] by whether a
     * submitted `id` resolves to an existing SYSTEM row in $existing.
     *
     * @param  array<int, array{id: ?int, name: string, color: ?string, group: string}>  $statusRows
     * @param  Collection<int, OpportunityWorkflowStatus>  $existing
     * @return array{0: array<int, array{id: ?int, name: string, color: ?string, group: string}>, 1: array<int, array{id: ?int, name: string, color: ?string, group: string}>}
     */
    private function partitionSubmitted(array $statusRows, Collection $existing): array
    {
        $systemRows = [];
        $customRows = [];

        foreach ($statusRows as $row) {
            $existingRow = $row['id'] !== null ? $existing->get($row['id']) : null;

            if ($existingRow !== null && $existingRow->isSystem()) {
                $systemRows[] = $row;
            } else {
                $customRows[] = $row;
            }
        }

        return [$systemRows, $customRows];
    }

    /**
     * @param  array<int, array{id: ?int, name: string, color: ?string, group: string}>  $systemRows
     * @param  Collection<int, OpportunityWorkflowStatus>  $existing
     */
    private function applySystemUpdates(array $systemRows, Collection $existing): void
    {
        foreach ($systemRows as $row) {
            /** @var OpportunityWorkflowStatus $status */
            $status = $existing->get($row['id']);

            $this->assertMutableSystemRow($status, $row);

            $status->fill(['name' => $row['name'], 'color' => $row['color']])->save();
        }
    }

    /**
     * Updates/creates every submitted custom row (in submission order),
     * deletes the customs left out, and returns the kept ids IN ORDER — the
     * sequence resequence() places between 'open' and 'closed'.
     *
     * @param  array<int, array{id: ?int, name: string, color: ?string, group: string}>  $customRows
     * @param  Collection<int, OpportunityWorkflowStatus>  $existing
     * @return array<int, int>
     */
    private function applyCustomSync(?int $workflowId, array $customRows, Collection $existing): array
    {
        $keptIds = [];

        foreach ($customRows as $row) {
            if ($row['id'] === null) {
                $created = OpportunityWorkflowStatus::query()->forceCreate([
                    'opportunity_workflow_id' => $workflowId,
                    'name' => $row['name'],
                    'color' => $row['color'],
                    'sort_order' => 0,
                    'system_key' => null,
                    'group' => $row['group'],
                ]);

                $keptIds[] = $created->id;

                continue;
            }

            $status = $existing->get($row['id']);

            if ($status === null || $status->isSystem()) {
                abort(422, "Unknown custom status id [{$row['id']}] for this workflow.");
            }

            $status->fill(['name' => $row['name'], 'color' => $row['color'], 'group' => $row['group']])->save();
            $keptIds[] = $status->id;
        }

        $existing
            ->filter(static fn (OpportunityWorkflowStatus $status): bool => ! $status->isSystem() && ! in_array($status->id, $keptIds, true))
            ->each(static fn (OpportunityWorkflowStatus $status) => $status->delete());

        return $keptIds;
    }

    /**
     * @param  array<int, int>  $orderedCustomIds
     */
    private function resequence(?int $workflowId, array $orderedCustomIds): void
    {
        OpportunityWorkflowStatus::query()
            ->where('opportunity_workflow_id', $workflowId)
            ->where('system_key', WorkflowStatusSystemKey::Open->value)
            ->update(['sort_order' => 0]);

        $sortOrder = self::STEP;

        foreach ($orderedCustomIds as $id) {
            OpportunityWorkflowStatus::query()->where('id', $id)->update(['sort_order' => $sortOrder]);
            $sortOrder += self::STEP;
        }

        foreach (WorkflowStatusSystemKey::closedKeys() as $key) {
            OpportunityWorkflowStatus::query()
                ->where('opportunity_workflow_id', $workflowId)
                ->where('system_key', $key->value)
                ->update(['sort_order' => $sortOrder]);
            $sortOrder += self::STEP;
        }
    }
}
