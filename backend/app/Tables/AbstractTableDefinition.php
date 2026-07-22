<?php

namespace App\Tables;

use App\Enums\AdvancedFilterType;
use App\Models\User;
use App\Services\Table\AdvancedFilterApplier;
use App\Tables\Concerns\InjectsDefaultIdColumn;
use App\Tables\Concerns\ResolvesEditableColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Shared resolution for every concrete TableDefinition.
 *
 * Lifted VERBATIM from the old UserTableService::config() + the whitelist
 * helpers (sortableColumns/filterableColumns), parameterized on the concrete
 * definition's columns()/filters()/actions() instead of config('tables.users').
 *
 * A concrete definition only declares its model (baseQuery), its catalogues
 * (columns/filters/actions), defaults, and the two row-level hooks
 * (mapRow/actionsFor) + dynamic option resolution (optionsFor()). The
 * cross-cutting parts (permission filtering of actions, dynamic option
 * resolution on columns/filters, whitelist derivation) live here once.
 */
abstract class AbstractTableDefinition implements TableDefinition
{
    use InjectsDefaultIdColumn;
    use ResolvesEditableColumns;

    public function resource(): string
    {
        return $this->domain();
    }

    /**
     * Fail-safe default authorization: delegate to the model's Policy `viewAny`.
     *
     * A definition must declare its model (modelClass()) and therefore inherits
     * the standard `viewAny` gate WITHOUT being able to "forget" the check or
     * return a hardcoded `true`. This is fail-closed: if no Policy/permission is
     * registered for the model, Gate::allows() returns false (table denied),
     * never fail-open. Override only to add domain-specific rules on top.
     */
    public function authorizeViewAny(User $actor): bool
    {
        return Gate::forUser($actor)->allows('viewAny', $this->modelClass());
    }

    /**
     * Dynamic options for a column/filter id (e.g. `roles` resolved per actor).
     * Concrete definitions override for columns whose options are not static.
     * Return null to keep the statically-declared `options` (if any).
     *
     * @return array<int, scalar>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        return null;
    }

    /**
     * Per-value badge metadata for a `badge` column (label/color/icon resolved
     * from a domain enum via App\Enums\Concerns\HasMeta::options()). Each entry
     * is an EnumMeta::toArray() shape. Return null to omit the `badges` key.
     *
     * The frontend renders the badge purely from this metadata, so it never has
     * to know the domain enum: values, labels, colors and icons all come from
     * the backend.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return null;
    }

    /**
     * The snake_case domain-enum key (config/config.php → form_enums) a `badge`
     * column maps to, e.g. `user_type` → `personal_data_type`. The frontend uses
     * it to localize the badge label from its own i18n resources
     * (`enums.<enumKey>.<value>`) instead of the backend-supplied label. Return
     * null to omit the `enumKey` key (and keep the backend label authoritative).
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return null;
    }

    /**
     * Resolve the table schema for the given actor.
     *
     * Columns and filters are static (visibility is a frontend concern), but
     * their options may be dynamic (resolved via optionsFor) and the action
     * catalogue is filtered to the keys the actor is allowed to use, so the UI
     * never advertises an action the actor can never perform.
     *
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array
    {
        $layout = $this->defaultColumnLayout();
        $editableIds = $this->editableColumnIds($actor);

        return [
            'resource' => $this->resource(),
            'columns' => array_map(
                fn (array $column): array => $this->resolveColumn($column, $actor, $layout, $editableIds),
                $this->columnsWithDefaultId(),
            ),
            'filters' => $this->resolveFilters($actor),
            'actions' => $this->resolveActions($actor),
            'defaultSort' => $this->defaultSort(),
            'defaultPagination' => $this->defaultPagination(),
            // Real columns the global quick-search spans (spec 0009). The frontend
            // shows the search box only when non-empty and builds its placeholder
            // from these columns' labels.
            'searchable' => $this->searchableColumnIds(),
            // Advanced-filter catalogue (spec 0032), ordered, internal fields
            // stripped. `appliedAdvancedFilters` defaults to null here; the
            // per-user persisted state (TableFilterStateService) overwrites it,
            // exactly like `filterState`/`filtersCustomized`.
            'advancedFilters' => $this->resolveAdvancedFilters(),
            'appliedAdvancedFilters' => null,
        ];
    }

    /**
     * Resolve a single column declaration into its client-facing shape.
     *
     * Presentation properties (visible/width/order) come from the layout and are
     * user-overridable (ADR-0004); structural/security properties (sortable/
     * filterable/filterType/hasFilterValues) come straight from the declaration
     * and are never user-overridable. `filterType` drives the frontend filter
     * widget (a set filter can sit on a text/badge-rendered column, e.g. the geo
     * columns), so it is part of the public contract. `hasFilterValues` (spec
     * 0004/0005) tells the frontend whether the column's Set Filter can
     * enumerate a discrete value list at all — false for COMPUTED columns with
     * no discrete list (a formatted address string, an aggregate count), which
     * also have no real DB column to `SELECT DISTINCT` on. `badges` is emitted
     * only for `badge` columns, keeping every other column byte-identical to
     * before.
     *
     * @param  array<string, mixed>  $column
     * @param  array<string, array{visible: bool, width: int|null, order: int}>  $layout
     * @param  array<int, string>  $editableIds
     * @return array<string, mixed>
     */
    private function resolveColumn(array $column, User $actor, array $layout, array $editableIds): array
    {
        $resolved = [
            'id' => $column['id'],
            'label' => $column['label'],
            'type' => $column['type'],
            // Presentation properties a user may override (ADR-0004).
            'visible' => $layout[$column['id']]['visible'],
            'width' => $layout[$column['id']]['width'],
            'order' => $layout[$column['id']]['order'],
            // Structural / security properties — NEVER user-overridable.
            'sortable' => $column['sortable'],
            'filterable' => $column['filterable'],
            'filterType' => $column['filterType'] ?? null,
            'hasFilterValues' => $this->hasFilterValues($column),
            // Inline cell-editing (spec 0053, D-2): already reduced for the
            // actor — a UI hint, never the authority (the PATCH endpoint
            // re-derives its own guards against the real row).
            'editable' => in_array($column['id'], $editableIds, true),
            'options' => $this->optionsFor($column['id'], $actor)
                ?? ($column['options'] ?? null),
        ];

        $badges = $this->badgesFor($column['id'], $actor);

        if ($badges !== null) {
            $resolved['badges'] = $badges;
        }

        $enumKey = $this->enumKeyFor($column['id'], $actor);

        if ($enumKey !== null) {
            $resolved['enumKey'] = $enumKey;
        }

        return $resolved;
    }

    /**
     * Whether the Set Filter (spec 0004/0005) can enumerate a discrete value
     * list for this column. Explicit `hasFilterValues` in the raw declaration
     * wins (COMPUTED columns with no discrete list — e.g. a formatted address
     * string, an aggregate count — declare it `false`); otherwise defaults to
     * true whenever the column is filterable with a declared filterType.
     *
     * @param  array<string, mixed>  $column
     */
    private function hasFilterValues(array $column): bool
    {
        if (array_key_exists('hasFilterValues', $column)) {
            return (bool) $column['hasFilterValues'];
        }

        return ($column['filterable'] ?? false) === true && ($column['filterType'] ?? null) !== null;
    }

    /**
     * Default presentation layout per column id (ADR-0004).
     *
     * `order` defaults to the 1-based declaration position when a column does not
     * declare an explicit `order`; `width` defaults to null (the frontend applies
     * its own default width). This is the single baseline used BOTH to render the
     * default config and to compute the sparse user delta on save, so the two can
     * never drift.
     *
     * @return array<string, array{visible: bool, width: int|null, order: int}>
     */
    public function defaultColumnLayout(): array
    {
        $layout = [];
        $position = 0;

        foreach ($this->columnsWithDefaultId() as $column) {
            $position++;

            $layout[$column['id']] = [
                'visible' => (bool) ($column['visible'] ?? true),
                'width' => isset($column['width']) ? (int) $column['width'] : null,
                'order' => (int) ($column['order'] ?? $position),
            ];
        }

        return $layout;
    }

    /**
     * Resolve the advanced-filter catalogue (spec 0032) into its FE-facing
     * shape: ordered by `order`, stripped of the INTERNAL `target`/`operator`
     * fields the definition uses to apply the filter server-side
     * (AdvancedFilterApplier) — mirrors resolveFilters()'s stripping of
     * `optionsSource`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveAdvancedFilters(): array
    {
        $descriptors = $this->advancedFilters();

        usort(
            $descriptors,
            static fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0),
        );

        return array_map(
            static function (array $descriptor): array {
                unset($descriptor['target'], $descriptor['operator']);

                return $descriptor;
            },
            $descriptors,
        );
    }

    /**
     * Resolve the filter catalogue: replace any declarative `optionsSource`
     * marker with the real dynamic options so no dead declarative field is left
     * for the frontend to resolve.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveFilters(User $actor): array
    {
        return array_map(
            function (array $filter) use ($actor): array {
                $columnId = $filter['columnId'] ?? null;

                if (is_string($columnId)) {
                    $dynamic = $this->optionsFor($columnId, $actor);

                    if ($dynamic !== null) {
                        unset($filter['optionsSource']);
                        $filter['options'] = $dynamic;
                    }
                }

                return $filter;
            },
            $this->filters(),
        );
    }

    /**
     * Filter the action catalogue down to keys the actor may use.
     * An action without a `permission` is available to any authenticated user.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveActions(User $actor): array
    {
        $resolved = [];

        foreach ($this->actions() as $action) {
            $permission = $action['permission'] ?? null;

            if ($permission !== null && ! $actor->can($permission)) {
                continue;
            }

            unset($action['permission']);
            $resolved[] = $action;
        }

        return array_values($resolved);
    }

    /**
     * Sortable real DB column names (whitelist for ORDER BY).
     *
     * @return array<int, string>
     */
    public function sortableColumnIds(): array
    {
        $ids = [];

        foreach ($this->columnsWithDefaultId() as $column) {
            if (($column['sortable'] ?? false) === true) {
                $ids[] = $column['id'];
            }
        }

        return $ids;
    }

    /**
     * Filterable real DB column names (or derived keys) — whitelist for WHERE.
     *
     * @return array<int, string>
     */
    public function filterableColumnIds(): array
    {
        return array_keys($this->filterableColumnMap());
    }

    /**
     * Real DB columns flagged `searchable` — the allow-list for the global
     * quick-search OR-LIKE (spec 0009). Only columns that map to a real base
     * column may be flagged (a bound `LIKE` runs on each); derived keys are
     * never eligible.
     *
     * @return array<int, string>
     */
    public function searchableColumnIds(): array
    {
        $ids = [];

        foreach ($this->columnsWithDefaultId() as $column) {
            if (($column['searchable'] ?? false) === true) {
                $ids[] = $column['id'];
            }
        }

        return $ids;
    }

    /**
     * Filterable columns keyed by id, each with its raw definition (used by the
     * service to resolve filterType/options safely). Derived columns (e.g.
     * `roles`) are included and handled specially by the definition.
     *
     * @return array<string, array<string, mixed>>
     */
    public function filterableColumnMap(): array
    {
        $columns = [];

        foreach ($this->columnsWithDefaultId() as $column) {
            if (($column['filterable'] ?? false) === true) {
                $columns[$column['id']] = $column;
            }
        }

        return $columns;
    }

    /**
     * Default: no advanced filters (spec 0032). A domain opts in by
     * overriding with its own catalogue.
     *
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return [];
    }

    /**
     * Advanced-filter ids whitelisted for the `advancedFilters` request key —
     * the `name` of every descriptor declared in advancedFilters().
     *
     * @return array<int, string>
     */
    public function advancedFilterableIds(): array
    {
        return array_column($this->advancedFilters(), 'name');
    }

    /**
     * Default: delegate to AdvancedFilterApplier. `target` (falling back to
     * `$name` when the descriptor omits it) is resolved server-side ONLY from
     * this definition's own catalogue — never client input — so it is trusted
     * as either a real DB column or, for `type: relation`, a plain Eloquent
     * relation method name. Always returns true for a well-formed descriptor;
     * false only when the descriptor carries no valid `type` (defence in
     * depth against a malformed catalogue entry).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        $type = $descriptor['type'] ?? null;

        if (! $type instanceof AdvancedFilterType) {
            return false;
        }

        $target = is_string($descriptor['target'] ?? null) ? $descriptor['target'] : $name;

        app(AdvancedFilterApplier::class)->apply($query, $type, $target, $value, $descriptor);

        return true;
    }

    /**
     * Default: no derived columns. Concrete definitions override to handle
     * derived columns (e.g. `roles`) that have no real DB column.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return false;
    }

    /**
     * Default: no derived columns. Concrete definitions override to ORDER BY a
     * derived value (e.g. a related column resolved via a correlated subquery).
     *
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return false;
    }

    /**
     * Default: no derived column owns its distinct-values resolution: let the
     * generic engine `SELECT DISTINCT` the real column. Concrete definitions
     * override for DERIVED columns (e.g. `roles`, `permissions`, geo names).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return null;
    }

    /**
     * Default: no derived searchable column — let the generic engine's plain
     * `orWhere($columnId, 'like', $pattern)` run against the real column.
     * Concrete definitions override for a DERIVED searchable column (e.g.
     * `city`/`street` on `operational-sites`, spec 0011).
     *
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return false;
    }

    /**
     * Default: a plain delete, identical to calling the model directly.
     * Concrete definitions override when the domain's single-delete endpoint
     * delegates to a Service that enforces a guard beyond the Policy check
     * (e.g. the last-super-admin guard, a protected-system-row guard).
     */
    public function deleteModel(Model $model): void
    {
        $model->delete();
    }

    // editableColumnIds()/authorizeUpdate()/updateCell() defaults (spec 0053)
    // live in ResolvesEditableColumns (file-size budget, engineering.md §6).
}
