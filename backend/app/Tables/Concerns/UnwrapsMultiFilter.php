<?php

namespace App\Tables\Concerns;

/**
 * Unwraps a possibly-`multi` filter payload (AG Grid's agMultiColumnFilter: a
 * Set sub-model + a typed condition sub-model) so a definition's derived
 * filter handler can dispatch each PRESENT sub-model to its own SET/condition
 * applier, both applying in AND. A payload that is NOT `multi` — a bare
 * condition, or a bare `{filterType:'set', values:[...]}` — is dispatched the
 * same way, so callers never special-case the pre-multi shape.
 *
 * Shared by UsersTableDefinition (primary_address/primary_contact) and
 * RolesTableDefinition (users_count): every COMPUTED column exposed through
 * the `multi` widget (spec 0004/0005) needs the exact same unwrap, only the
 * per-column SET/condition appliers differ.
 */
trait UnwrapsMultiFilter
{
    /**
     * @param  array<string, mixed>  $filter
     * @param  callable(array<string, mixed>): void  $applySet
     * @param  callable(array<string, mixed>): void  $applyCondition
     */
    private function dispatchDerivedFilter(array $filter, callable $applySet, callable $applyCondition): void
    {
        $filterType = $filter['filterType'] ?? null;

        if ($filterType === 'multi') {
            $subModels = $filter['filterModels'] ?? null;

            if (! is_array($subModels)) {
                return;
            }

            foreach ($subModels as $subModel) {
                if (is_array($subModel) && $subModel !== []) {
                    $this->dispatchDerivedFilter($subModel, $applySet, $applyCondition);
                }
            }

            return;
        }

        if ($filterType === 'set' || array_key_exists('values', $filter)) {
            $applySet($filter);

            return;
        }

        $applyCondition($filter);
    }
}
