<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Splits the single terminal 'closed' system row of every
 * opportunity_workflow_statuses set (a workflow's own, or the global default
 * set) into the two closed-outcome rows 'closed_won'/'closed_lost' (spec 0047,
 * revised AC-004): the closed phase now carries its outcome. Custom rows are
 * left untouched; only the pinned system rows are regenerated (delete +
 * recreate with default names), placed open-first / closed-last.
 *
 * A data migration (not a schema change): the `system_key`/`group` columns
 * already fit the new 16-char values. The committed create-migration seeded
 * only the global default set, but this iterates EVERY existing set so any
 * per-workflow sets created before this runs are converted too.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->setIds() as $workflowId) {
            $this->deleteSystemRows($workflowId);

            $sortOrder = $this->firstClosedSortOrder($workflowId);

            $this->insertSystemRows($workflowId, [
                ['name' => 'Aperta', 'sort_order' => 0, 'system_key' => 'open', 'group' => 'open'],
                ['name' => 'Chiusa positiva', 'sort_order' => $sortOrder, 'system_key' => 'closed_won', 'group' => 'closed_won'],
                ['name' => 'Chiusa negativa', 'sort_order' => $sortOrder + 10, 'system_key' => 'closed_lost', 'group' => 'closed_lost'],
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->setIds() as $workflowId) {
            $this->deleteSystemRows($workflowId);

            $this->insertSystemRows($workflowId, [
                ['name' => 'Aperta', 'sort_order' => 0, 'system_key' => 'open', 'group' => 'open'],
                ['name' => 'Chiusa', 'sort_order' => $this->firstClosedSortOrder($workflowId), 'system_key' => 'closed', 'group' => 'closed'],
            ]);
        }
    }

    /**
     * @return Collection<int, int|null>
     */
    private function setIds(): Collection
    {
        return DB::table('opportunity_workflow_statuses')
            ->select('opportunity_workflow_id')
            ->distinct()
            ->pluck('opportunity_workflow_id');
    }

    private function deleteSystemRows(?int $workflowId): void
    {
        $this->scope($workflowId)->whereNotNull('system_key')->delete();
    }

    /**
     * The sort_order for the first terminal row: right after the last
     * surviving custom row (STEP=10 apart), or 10 when the set has no customs.
     */
    private function firstClosedSortOrder(?int $workflowId): int
    {
        return (int) $this->scope($workflowId)->max('sort_order') + 10;
    }

    /**
     * @param  array<int, array{name: string, sort_order: int, system_key: string, group: string}>  $rows
     */
    private function insertSystemRows(?int $workflowId, array $rows): void
    {
        $now = now();

        DB::table('opportunity_workflow_statuses')->insert(array_map(
            static fn (array $row): array => [
                'opportunity_workflow_id' => $workflowId,
                'name' => $row['name'],
                'color' => null,
                'sort_order' => $row['sort_order'],
                'system_key' => $row['system_key'],
                'group' => $row['group'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $rows,
        ));
    }

    private function scope(?int $workflowId): Builder
    {
        $query = DB::table('opportunity_workflow_statuses');

        return $workflowId === null
            ? $query->whereNull('opportunity_workflow_id')
            : $query->where('opportunity_workflow_id', $workflowId);
    }
};
