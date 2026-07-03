<?php

namespace App\Services;

use App\DataObjects\Table\DistinctValuesResult;
use App\DataObjects\Table\RowsResult;
use App\Models\User;
use App\Services\Table\FilterApplier;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic SSRM engine for the domain-driven Table framework.
 *
 * Holds the SINGLE copy of the security-critical AG Grid SSRM logic (lifted
 * verbatim from the old UserTableService::rows() and its helpers): translate
 * startRow/endRow → offset/limit with a MAX_LIMIT cap, apply sortModel/
 * filterModel against the definition's strict sortable/filterable whitelist
 * using bound query-builder calls only (never raw user input in SQL), cap
 * set-filter cardinality, paginate, then map each row + attach the per-row
 * actions[] via the definition's Policy hooks. Per-type filter branches
 * (text/number/boolean/date/set/multi/combined) live in the injected
 * FilterApplier collaborator (spec 0004) to keep this file focused on
 * orchestration.
 *
 * It operates ONLY through the resolved TableDefinition (baseQuery, columns,
 * mapRow, actionsFor, applyDerivedFilter), so every domain inherits the exact
 * same anti-injection + per-row-authorization behavior. No behavioral change
 * vs ADR-0001's users-specific implementation.
 */
class TableService
{
    /**
     * Maximum rows returnable in a single SSRM block. Mirrors
     * BaseApiController::MAX_LIMIT and is also enforced by the FormRequest.
     */
    private const int MAX_LIMIT = 100;

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * Execute the SSRM query and return the rows + total for the envelope.
     *
     * @param  array{startRow: int, endRow: int, sortModel?: array<int, array<string, mixed>>, filterModel?: array<string, array<string, mixed>>, search?: string|null}  $payload
     */
    public function rows(TableDefinition $definition, User $actor, array $payload): RowsResult
    {
        $offset = max(0, (int) $payload['startRow']);
        $limit = min(self::MAX_LIMIT, max(1, (int) $payload['endRow'] - $offset));

        $query = $definition->baseQuery();

        $this->applyFilters($definition, $query, $payload['filterModel'] ?? []);
        $this->applySearch($definition, $query, $payload['search'] ?? null);

        $total = (clone $query)->count();

        $this->applySorting($definition, $query, $payload['sortModel'] ?? []);

        /** @var array<int, Model> $models */
        $models = $query->offset($offset)->limit($limit)->get()->all();

        $items = array_map(
            function (Model $row) use ($definition, $actor): array {
                $mapped = $definition->mapRow($actor, $row);
                $mapped['actions'] = $definition->actionsFor($actor, $row);

                return $mapped;
            },
            $models,
        );

        return new RowsResult(
            items: $items,
            total: $total,
            offset: $offset,
            limit: $limit,
        );
    }

    /**
     * Distinct values for a single filterable column (Excel-like set filter),
     * scoped by the filters active on every OTHER column — the target column
     * never auto-restricts its own list. Delegates to the definition's
     * distinctValues() hook for DERIVED columns (no real DB column, e.g.
     * `roles`); falls back to a plain `SELECT DISTINCT` on the real column.
     *
     * Fetches one value beyond `$limit` so `hasMore` reflects real truncation
     * without a second COUNT query.
     *
     * @param  array<string, array<string, mixed>>  $filterModel
     */
    public function distinctValues(
        TableDefinition $definition,
        User $actor,
        string $columnId,
        ?string $search,
        array $filterModel,
        int $limit,
    ): DistinctValuesResult {
        $filterable = $definition->filterableColumnMap();

        if (! array_key_exists($columnId, $filterable)) {
            return new DistinctValuesResult(values: [], hasMore: false);
        }

        $columnConfig = $filterable[$columnId];

        // COMPUTED columns with no discrete value list (e.g. a formatted
        // address string, an aggregate count) declare hasFilterValues=false:
        // there is no real DB column to SELECT DISTINCT on, so the generic
        // fallback must never be reached for them (defence in depth against
        // a 500 — see spec 0004/0005 AC-016..018). Resolver-backed derived
        // columns (roles/geo/user_type/permissions) keep hasFilterValues=true
        // and are unaffected.
        if (($columnConfig['hasFilterValues'] ?? true) === false) {
            return new DistinctValuesResult(values: [], hasMore: false);
        }

        $query = $definition->baseQuery();

        unset($filterModel[$columnId]); // Excel behaviour: never self-restrict.
        $this->applyFilters($definition, $query, $filterModel);

        $fetchLimit = $limit + 1;
        $resolved = $definition->distinctValues($actor, $columnId, $columnConfig, $search, $query, $fetchLimit);
        $values = $resolved ?? $this->distinctFromColumn($query, $columnId, $search, $fetchLimit);

        return new DistinctValuesResult(
            values: array_slice($values, 0, $limit),
            hasMore: count($values) > $limit,
        );
    }

    /**
     * Plain `SELECT DISTINCT` fallback for a real DB column: optional
     * case-insensitive substring search (bound, LIKE-escaped), sorted, capped.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    private function distinctFromColumn(Builder $query, string $column, ?string $search, int $limit): array
    {
        $clone = clone $query;

        if ($search !== null && $search !== '') {
            $clone->where($column, 'like', '%'.$this->filterApplier->escapeLike($search).'%');
        }

        return $clone->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->limit($limit)
            ->pluck($column)
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * Apply whitelisted filters to the query.
     *
     * SECURITY: only keys present in the definition's filterable whitelist are
     * honoured; every column is resolved to a real DB column name from the
     * definition and every value is passed as a bound parameter via the query
     * builder — never interpolated into SQL. Derived columns (e.g. `roles`) are
     * delegated to the definition's applyDerivedFilter hook.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, array<string, mixed>>  $filterModel
     */
    private function applyFilters(TableDefinition $definition, Builder $query, array $filterModel): void
    {
        $filterable = $definition->filterableColumnMap();

        foreach ($filterModel as $columnId => $filter) {
            if (! array_key_exists($columnId, $filterable)) {
                continue; // not whitelisted — ignore defensively (FormRequest already 422s)
            }

            if (! is_array($filter)) {
                continue;
            }

            // Derived columns (no real DB column) are handled by the definition.
            if ($definition->applyDerivedFilter($query, $columnId, $filterable[$columnId], $filter)) {
                continue;
            }

            $this->filterApplier->apply($query, $columnId, $filterable[$columnId], $filter);
        }
    }

    /**
     * Apply the global quick-search (spec 0009): a single grouped OR-LIKE over
     * the definition's `searchableColumnIds()` allow-list.
     *
     * SECURITY: the columns come exclusively from the definition's server-side
     * allow-list (never from the request), and the term is a LIKE-escaped bound
     * parameter — never interpolated into SQL. The OR group is wrapped in its
     * own closure so it AND-combines with any active column filters instead of
     * widening them.
     *
     * @param  Builder<Model>  $query
     */
    private function applySearch(TableDefinition $definition, Builder $query, ?string $search): void
    {
        $term = $search === null ? '' : trim($search);

        if ($term === '') {
            return;
        }

        $columns = $definition->searchableColumnIds();

        if ($columns === []) {
            return;
        }

        $pattern = '%'.$this->filterApplier->escapeLike($term).'%';

        $query->where(function (Builder $group) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $group->orWhere($column, 'like', $pattern);
            }
        });
    }

    /**
     * Apply whitelisted sorting. Only columns flagged `sortable` in the
     * definition are accepted; direction is constrained to asc/desc. Both are
     * validated upstream by the FormRequest — this is defence in depth.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, array<string, mixed>>  $sortModel
     */
    private function applySorting(TableDefinition $definition, Builder $query, array $sortModel): void
    {
        $sortable = $definition->sortableColumnIds();
        $applied = false;

        foreach ($sortModel as $sort) {
            $colId = $sort['colId'] ?? null;

            if (! is_string($colId) || ! in_array($colId, $sortable, true)) {
                continue;
            }

            $direction = ($sort['sort'] ?? null) === 'desc' ? 'desc' : 'asc';

            // Derived columns (no real DB column, e.g. user_type/geo) are sorted
            // by the definition; everything else is a plain ORDER BY.
            if (! $definition->applyDerivedSort($query, $colId, $direction)) {
                $query->orderBy($colId, $direction);
            }

            $applied = true;
        }

        if (! $applied) {
            $this->applyDefaultSort($definition, $query, $sortable);
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $sortable
     */
    private function applyDefaultSort(TableDefinition $definition, Builder $query, array $sortable): void
    {
        foreach ($definition->defaultSort() as $sort) {
            $colId = $sort['columnId'] ?? null;

            if (is_string($colId) && in_array($colId, $sortable, true)) {
                $direction = ($sort['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

                if (! $definition->applyDerivedSort($query, $colId, $direction)) {
                    $query->orderBy($colId, $direction);
                }
            }
        }
    }
}
