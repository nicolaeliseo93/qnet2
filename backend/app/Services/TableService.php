<?php

namespace App\Services;

use App\DataObjects\Table\RowsResult;
use App\Models\User;
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
 * using bound query-builder calls only (never raw user input in SQL), escape
 * LIKE wildcards, cap set-filter cardinality, paginate, then map each row +
 * attach the per-row actions[] via the definition's Policy hooks.
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

    /**
     * Execute the SSRM query and return the rows + total for the envelope.
     *
     * @param  array{startRow: int, endRow: int, sortModel?: array<int, array<string, mixed>>, filterModel?: array<string, array<string, mixed>>}  $payload
     */
    public function rows(TableDefinition $definition, User $actor, array $payload): RowsResult
    {
        $offset = max(0, (int) $payload['startRow']);
        $limit = min(self::MAX_LIMIT, max(1, (int) $payload['endRow'] - $offset));

        $query = $definition->baseQuery();

        $this->applyFilters($definition, $query, $payload['filterModel'] ?? []);

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

            $this->applyColumnFilter($query, $columnId, $filterable[$columnId], $filter);
        }
    }

    /**
     * Apply a scalar/date/set filter to a real DB column.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    private function applyColumnFilter(Builder $query, string $column, array $columnConfig, array $filter): void
    {
        $filterType = $columnConfig['filterType'] ?? null;

        // Set filter (enum columns, e.g. locale): WHERE column IN (?, ?...).
        if ($filterType === 'set') {
            $values = $filter['values'] ?? null;

            if (is_array($values) && $values !== []) {
                $options = $columnConfig['options'] ?? [];
                $clean = array_values(array_filter(
                    $values,
                    static fn ($value): bool => is_scalar($value) && in_array($value, $options, true),
                ));

                if ($clean !== []) {
                    $query->whereIn($column, $clean);
                }
            }

            return;
        }

        // Date filter: equals / range on a datetime column.
        if ($filterType === 'date') {
            $this->applyDateFilter($query, $column, $filter);

            return;
        }

        // Text filter (name, email): bound LIKE/equality, value never inlined.
        $value = $filter['filter'] ?? null;

        if (! is_scalar($value) || $value === '') {
            return;
        }

        $value = (string) $value;
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'contains';

        match ($type) {
            'equals' => $query->where($column, '=', $value),
            'notEqual' => $query->where($column, '!=', $value),
            'startsWith' => $query->where($column, 'like', $this->escapeLike($value).'%'),
            'endsWith' => $query->where($column, 'like', '%'.$this->escapeLike($value)),
            'notContains' => $query->where($column, 'not like', '%'.$this->escapeLike($value).'%'),
            default => $query->where($column, 'like', '%'.$this->escapeLike($value).'%'),
        };
    }

    /**
     * Apply a date filter (equals or inRange) on a datetime column.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyDateFilter(Builder $query, string $column, array $filter): void
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';
        $from = is_string($filter['dateFrom'] ?? null) ? $filter['dateFrom'] : null;
        $to = is_string($filter['dateTo'] ?? null) ? $filter['dateTo'] : null;

        if ($type === 'inRange' && $from !== null && $to !== null) {
            $query->whereBetween($column, [$from, $to]);

            return;
        }

        if ($from === null) {
            return;
        }

        match ($type) {
            'greaterThan' => $query->where($column, '>', $from),
            'lessThan' => $query->where($column, '<', $from),
            'notEqual' => $query->whereDate($column, '!=', $from),
            default => $query->whereDate($column, '=', $from),
        };
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

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
