<?php

declare(strict_types=1);

namespace App\Support\OpportunityWorkflows;

use App\Models\OpportunityWorkflowCriterion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Batch-resolves the human-readable `name` a criterion's `value_id` points at
 * (spec 0047 Lane A: `value_label` on OpportunityWorkflowResource, the
 * `criteria_values` table column) — grouped by CriterionFieldRegistry::
 * existsTable() and read with ONE query per distinct table, never per-row
 * (anti-N+1), shared by both consumers so the resolution logic lives once.
 */
final class CriterionValueLabelResolver
{
    /**
     * @param  Collection<int, OpportunityWorkflowCriterion>  $criteria
     * @return array<int, string> criterion id => resolved label (falls back
     *                            to the raw value_id, stringified, when the referenced row no longer
     *                            exists)
     */
    public static function resolve(Collection $criteria): array
    {
        if ($criteria->isEmpty()) {
            return [];
        }

        $namesByTable = $criteria
            ->groupBy(static fn (OpportunityWorkflowCriterion $criterion): string => CriterionFieldRegistry::existsTable($criterion->field))
            ->map(static fn (Collection $rows, string $table): Collection => DB::table($table)
                ->whereIn('id', $rows->pluck('value_id')->unique()->all())
                ->pluck('name', 'id'));

        return $criteria
            ->mapWithKeys(static function (OpportunityWorkflowCriterion $criterion) use ($namesByTable): array {
                $table = CriterionFieldRegistry::existsTable($criterion->field);
                $label = $namesByTable->get($table)?->get($criterion->value_id);

                return [$criterion->id => $label ?? (string) $criterion->value_id];
            })
            ->all();
    }
}
