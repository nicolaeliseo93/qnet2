<?php

namespace App\Services\BusinessFunctions;

use App\Models\BusinessFunction;
use Illuminate\Support\Collection;

/**
 * Read-side resolution of the business-function tree (spec 0010 REV):
 * ancestor chains (anti-cycle guard) and descendant ids (for-select's
 * `exclude_descendants_of`). Walks `parent_id` in PHP rather than a raw
 * recursive SQL query — consciously duplicated from
 * ProductCategories\CategoryHierarchy (correctness/portability over cleverness,
 * works identically on the SQLite dev/test driver and MySQL production; a
 * shared walker could be extracted later if a third hierarchy appears).
 */
final class BusinessFunctionHierarchy
{
    /**
     * Defensive cap on the ancestor/descendant walk: the write-side
     * anti-cycle guard (BusinessFunctionService) prevents a real cycle from
     * ever being persisted, so this only guards against corrupted data
     * looping forever.
     */
    private const int MAX_DEPTH = 100;

    /**
     * $function's ancestors, ROOT-FIRST (does not include $function itself).
     *
     * @return Collection<int, BusinessFunction>
     */
    public function ancestors(BusinessFunction $function): Collection
    {
        $chain = [];
        $currentId = $function->parent_id;
        $depth = 0;

        while ($currentId !== null && $depth < self::MAX_DEPTH) {
            $parent = BusinessFunction::find($currentId);

            if ($parent === null) {
                break;
            }

            $chain[] = $parent;
            $currentId = $parent->parent_id;
            $depth++;
        }

        return collect(array_reverse($chain));
    }

    /**
     * Whether $candidateAncestorId is among $function's ancestors — the
     * anti-cycle check: reparenting $function under a node that descends
     * from $function would create a cycle.
     */
    public function isAncestorOf(BusinessFunction $function, int $candidateAncestorId): bool
    {
        return $this->ancestors($function)->contains('id', $candidateAncestorId);
    }

    /**
     * Every DESCENDANT id of $functionId (recursive, excludes itself), via a
     * single id/parent_id projection grouped in memory — feeds the for-select
     * `exclude_descendants_of` parent picker, never a query per row.
     *
     * @return array<int, int>
     */
    public function descendantIds(int $functionId): array
    {
        $byParent = BusinessFunction::query()->select('id', 'parent_id')->get()->groupBy('parent_id');

        $ids = [];
        $visited = [];
        $queue = $byParent->get($functionId, collect())->pluck('id')->all();

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $ids[] = $currentId;

            foreach ($byParent->get($currentId, collect()) as $child) {
                $queue[] = $child->id;
            }
        }

        return $ids;
    }
}
