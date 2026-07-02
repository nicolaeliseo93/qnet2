<?php

namespace App\Tables;

use App\Models\User;
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

        return [
            'resource' => $this->resource(),
            'columns' => array_map(
                fn (array $column): array => $this->resolveColumn($column, $actor, $layout),
                $this->columns(),
            ),
            'filters' => $this->resolveFilters($actor),
            'actions' => $this->resolveActions($actor),
            'defaultSort' => $this->defaultSort(),
            'defaultPagination' => $this->defaultPagination(),
        ];
    }

    /**
     * Resolve a single column declaration into its client-facing shape.
     *
     * Presentation properties (visible/width/order) come from the layout and are
     * user-overridable (ADR-0004); structural/security properties (sortable/
     * filterable/filterType) come straight from the declaration and are never
     * user-overridable. `filterType` drives the frontend filter widget (a set
     * filter can sit on a text/badge-rendered column, e.g. the geo columns), so
     * it is part of the public contract. `badges` is emitted only for `badge`
     * columns, keeping every other column byte-identical to before.
     *
     * @param  array<string, mixed>  $column
     * @param  array<string, array{visible: bool, width: int|null, order: int}>  $layout
     * @return array<string, mixed>
     */
    private function resolveColumn(array $column, User $actor, array $layout): array
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

        foreach ($this->columns() as $column) {
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

        foreach ($this->columns() as $column) {
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
     * Filterable columns keyed by id, each with its raw definition (used by the
     * service to resolve filterType/options safely). Derived columns (e.g.
     * `roles`) are included and handled specially by the definition.
     *
     * @return array<string, array<string, mixed>>
     */
    public function filterableColumnMap(): array
    {
        $columns = [];

        foreach ($this->columns() as $column) {
            if (($column['filterable'] ?? false) === true) {
                $columns[$column['id']] = $column;
            }
        }

        return $columns;
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
}
