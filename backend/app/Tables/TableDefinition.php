<?php

namespace App\Tables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for a single domain's table (config + rows).
 *
 * A definition declares EVERYTHING about its table: the base query, the
 * column/filter/action catalogues, the default sort/pagination, how a model
 * maps to a row, and which row-actions are allowed for a given actor. The
 * generic TableService + TableController operate ONLY through this contract,
 * so the security-critical SSRM engine lives in exactly one place and every
 * domain inherits it identically.
 *
 * @phpstan-type ColumnDefinition array{id: string, label: string, type: string, visible: bool, sortable: bool, filterable: bool, filterType?: string|null, hasFilterValues?: bool, options?: array<int, scalar>|null, badges?: array<int, array<string, mixed>>|null, permission?: string|null}
 * @phpstan-type FilterDefinition array{columnId: string, type: string, options?: array<int, scalar>|null, optionsResolver?: callable}
 * @phpstan-type ActionDefinition array{key: string, label: string, icon: string, type: string, confirm: bool, permission?: string|null}
 */
interface TableDefinition
{
    /**
     * Permission prefix / domain key, e.g. "users". Also the `resource` field
     * returned in the config so the frontend can pick its renderer registry.
     */
    public function domain(): string;

    /**
     * The `resource` key exposed to the frontend (defaults to domain()).
     */
    public function resource(): string;

    /**
     * The Eloquent model class this table is built on. Drives the fail-safe
     * default `viewAny` authorization (via the model's Policy) so a definition
     * can never accidentally expose its table without a permission check.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string;

    /**
     * Authorize table access (viewAny). False → 403 on both endpoints.
     *
     * Defaults (in AbstractTableDefinition) to the model's Policy `viewAny`
     * (fail-safe): a definition that forgets to override still requires the
     * permission. Override only to add domain-specific rules.
     */
    public function authorizeViewAny(User $actor): bool;

    /**
     * Base Eloquent query (with eager loads, no N+1).
     *
     * @return Builder<Model>
     */
    public function baseQuery(): Builder;

    /**
     * Column catalogue (raw declarative definitions).
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array;

    /**
     * Filter catalogue (raw declarative definitions).
     *
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array;

    /**
     * Action catalogue (raw declarative definitions).
     *
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array;

    /**
     * Default sort applied when the request carries no (whitelisted) sortModel.
     *
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array;

    /**
     * Default pagination hint for the initial grid state.
     *
     * @return array{limit: int}
     */
    public function defaultPagination(): array;

    /**
     * Map one model → row array (real fields only, hidden never exposed).
     * Must NOT include `actions` (the service attaches it via actionsFor()).
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array;

    /**
     * Allowed action keys for THIS row, via the domain Policy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array;

    /**
     * Real DB column names whitelisted for ORDER BY.
     *
     * @return array<int, string>
     */
    public function sortableColumnIds(): array;

    /**
     * Real DB column names (or derived keys) whitelisted for WHERE.
     *
     * @return array<int, string>
     */
    public function filterableColumnIds(): array;

    /**
     * Resolved config for the given actor: columns/filters/actions filtered by
     * permission, dynamic options resolved. This is the GET /columns payload
     * (the DEFAULT layout, before any per-user preference is merged in).
     *
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array;

    /**
     * Default presentation layout per column id: the authoritative baseline
     * against which a user's saved preferences are merged (read) and diffed
     * (write) — see ADR-0004. Keys are column ids; values carry only the
     * user-overridable presentation properties.
     *
     * @return array<string, array{visible: bool, width: int|null, order: int}>
     */
    public function defaultColumnLayout(): array;

    /**
     * Filterable columns keyed by DB column name, each with its raw definition
     * (used by the service to resolve filterType/options safely).
     *
     * @return array<string, array<string, mixed>>
     */
    public function filterableColumnMap(): array;

    /**
     * Hook for DERIVED columns (no real DB column, e.g. `roles` → whereHas).
     * Return true when the definition handled the filter itself; false to let
     * the generic engine apply it against the real column `$columnId`.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool;

    /**
     * Hook for DERIVED columns that have no real DB column to ORDER BY (e.g.
     * `user_type` resolved from a relation). Return true when the definition
     * applied the sort itself; false to let the generic engine ORDER BY the real
     * column `$columnId`. `$direction` is already normalized to `asc`/`desc`.
     *
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool;

    /**
     * Hook for the Excel-like distinct-values endpoint (spec 0004). Return the
     * resolved list of distinct scalar values (as strings, already
     * search-filtered and capped to `$limit`) when the definition owns a
     * DERIVED column with no real DB column to `SELECT DISTINCT` on (e.g.
     * `roles`, `permissions`, geo names). Return null to let the generic
     * engine run a plain `SELECT DISTINCT` on the real column `$columnId`.
     *
     * `$query` is the base query with every OTHER active filter already
     * applied (never the target column's own filter — the column must not
     * auto-restrict its own list).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array;
}
