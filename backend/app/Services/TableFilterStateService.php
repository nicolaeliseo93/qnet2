<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTableFilter;
use App\Tables\TableDefinition;

/**
 * Per-user table filter state: merge into config (read), upsert / reset (write).
 * Sibling of TablePreferenceService (ADR-0004) for the AG Grid filterModel, so
 * filters survive a page reload.
 *
 * Domain-agnostic: it operates ONLY through the resolved TableDefinition and the
 * generic user_table_filters store, so every table inherits filter persistence
 * with no per-domain code.
 *
 * SECURITY: the stored filterModel is restricted to the definition's FILTERABLE
 * columns (filterableColumnIds) on every write and every read, so a stale/removed
 * column can never leak back into the grid, and the persisted keys always match
 * the same allow-list the SSRM query engine enforces (TableRowsRequest). Unlike
 * column preferences this is NOT a sparse delta — filters have no default to diff
 * against, so the applied model is stored (and cleared) as a whole.
 */
class TableFilterStateService
{
    /**
     * Merge the actor's saved filter state into a resolved config so the frontend
     * can re-apply it on mount. Adds two keys mirroring the preferences merge:
     *  - `filterState`: the saved filterModel (object; `{}` when none);
     *  - `filtersCustomized`: whether there is a saved filter to reset.
     *
     * @param  array<string, mixed>  $config  the definition's resolveConfig() output
     * @return array<string, mixed>
     */
    public function applyTo(array $config, TableDefinition $definition, User $actor): array
    {
        $saved = $this->savedFor($definition, $actor);

        // Emit as an object so an empty state serializes to `{}` (a valid empty
        // AG Grid filterModel), never `[]`.
        $config['filterState'] = (object) $saved;
        $config['filtersCustomized'] = $saved !== [];

        return $config;
    }

    /**
     * Persist the actor's applied filterModel for this domain (idempotent upsert
     * on (user_id, domain)). Keys outside the definition's filterable allow-list
     * are dropped defensively (the FormRequest already 422s them). An empty model
     * clears the saved state entirely, so there is never an orphan row.
     *
     * @param  array<string, mixed>  $filterModel
     */
    public function save(TableDefinition $definition, User $actor, array $filterModel): void
    {
        $filtered = $this->allowlist($definition, $filterModel);

        if ($filtered === []) {
            $this->reset($definition, $actor);

            return;
        }

        UserTableFilter::query()->updateOrCreate(
            ['user_id' => $actor->id, 'domain' => $definition->domain()],
            ['filters' => $filtered],
        );
    }

    /**
     * Reset the actor's filter state for this table by removing the saved row.
     * Explicit user action only — filters are never auto-deleted.
     */
    public function reset(TableDefinition $definition, User $actor): void
    {
        UserTableFilter::query()
            ->where('user_id', $actor->id)
            ->where('domain', $definition->domain())
            ->delete();
    }

    /**
     * The actor's stored filterModel for this domain, restricted to columns still
     * filterable in the definition (empty when none).
     *
     * @return array<string, mixed>
     */
    private function savedFor(TableDefinition $definition, User $actor): array
    {
        $filter = UserTableFilter::query()
            ->where('user_id', $actor->id)
            ->where('domain', $definition->domain())
            ->first();

        $model = $filter?->filters ?? [];

        return is_array($model) ? $this->allowlist($definition, $model) : [];
    }

    /**
     * Keep only the entries whose column id is whitelisted for filtering by the
     * definition — the same allow-list the SSRM query engine enforces.
     *
     * @param  array<string, mixed>  $filterModel
     * @return array<string, mixed>
     */
    private function allowlist(TableDefinition $definition, array $filterModel): array
    {
        $allowed = array_flip($definition->filterableColumnIds());

        return array_intersect_key($filterModel, $allowed);
    }
}
