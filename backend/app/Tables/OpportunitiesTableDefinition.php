<?php

namespace App\Tables;

use App\Models\Opportunity;
use App\Models\User;
use App\Tables\Opportunities\OpportunityAdvancedFilterCatalog;
use App\Tables\Opportunities\OpportunityColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `opportunities` domain (spec 0040).
 *
 * `name`/`estimated_value`/`success_probability`/`start_date`/
 * `expected_close_date`/`created_at` are real columns handled entirely by the
 * generic engine. `registry`/`referent`/`commercial`/`supervisor`/`source`/
 * `opportunity_status` (spec 0043, D-3) are simple relation-name derived
 * columns (own FK on the opportunity), resolved generically via
 * DERIVED_RELATIONS — a `whereHas` set filter
 * (allow-listed columns only, never orderByRaw/whereRaw on raw input —
 * backend.md §8) and a correlated subquery sort, mirroring
 * LeadsTableDefinition. Amendment rev.3: `product_category`/
 * `business_function` are AGGREGATED to-many columns (via `productLines`,
 * resolved via AGGREGATED_RELATIONS) — filterable but not sortable.
 */
class OpportunitiesTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Simple (single-hop) relation-name derived columns: relation accessor,
     * related table and owning FK column, keyed by the derived column id.
     * `commercial`/`supervisor` both resolve to their own FK — never the
     * plain `referent_id`/`source_id` used by other columns.
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'registry' => ['relation' => 'registry', 'table' => 'registries', 'fk' => 'registry_id'],
        'referent' => ['relation' => 'referent', 'table' => 'referents', 'fk' => 'referent_id'],
        'commercial' => ['relation' => 'commercial', 'table' => 'referents', 'fk' => 'commercial_id'],
        'supervisor' => ['relation' => 'supervisor', 'table' => 'users', 'fk' => 'supervisor_id'],
        'source' => ['relation' => 'source', 'table' => 'sources', 'fk' => 'source_id'],
        // spec 0043: the mandatory working-state classification.
        'opportunity_status' => ['relation' => 'opportunityStatus', 'table' => 'opportunity_statuses', 'fk' => 'opportunity_status_id'],
    ];

    /**
     * Aggregated (to-many, via `productLines`) derived columns: the nested
     * relation path, the related table and the FK column on
     * `opportunity_product_lines` pointing to it (amendment rev.3 — the
     * former single `business_function_id`/`product_category_id` columns on
     * `opportunities` no longer exist).
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array AGGREGATED_RELATIONS = [
        'product_category' => ['relation' => 'productLines.productCategory', 'table' => 'product_categories', 'fk' => 'product_category_id'],
        'business_function' => ['relation' => 'productLines.businessFunction', 'table' => 'business_functions', 'fk' => 'business_function_id'],
    ];

    public function domain(): string
    {
        return 'opportunities';
    }

    /**
     * @return class-string<Opportunity>
     */
    public function modelClass(): string
    {
        return Opportunity::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives OpportunityPolicy::viewAny
    // from modelClass() (opportunities.viewAny).

    /**
     * @return Builder<Opportunity>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every relation mapRow touches to avoid N+1 across the page.
        // supervisor/managers pull their avatar relation too, so each row can
        // project the inline avatar (data URI) without a per-row query.
        return Opportunity::query()->with([
            'registry', 'referent', 'commercial', 'supervisor.avatar', 'source', 'opportunityStatus',
            'managers.avatar', 'productLines.businessFunction', 'productLines.productCategory',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return OpportunityColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return OpportunityColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return OpportunityColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return OpportunityAdvancedFilterCatalog::advancedFilters();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Map an Opportunity to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Opportunity $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'registry' => $this->summarize($row->registry),
            'referent' => $this->summarize($row->referent),
            'commercial' => $this->summarize($row->commercial),
            'supervisor' => $this->userSummary($row->supervisor),
            'managers' => $row->managers->map(fn (User $user): array => $this->userSummary($user))->all(),
            'source' => $this->summarize($row->source),
            'opportunity_status' => $this->summarize($row->opportunityStatus),
            'product_category' => $this->summarizeNames($row->productLines->pluck('productCategory')),
            'business_function' => $this->summarizeNames($row->productLines->pluck('businessFunction')),
            'estimated_value' => $row->estimated_value,
            'success_probability' => $row->success_probability,
            'start_date' => $row->start_date,
            'expected_close_date' => $row->expected_close_date,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * A person summary carrying the inline avatar (data URI) so the supervisor
     * and managers columns render a real avatar, not just initials — mirrors
     * BusinessFunctionsTableDefinition::userSummary(). Null when unset.
     *
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarDataUri(),
        ];
    }

    /**
     * Display value for an AGGREGATED to-many column (amendment rev.3): the
     * distinct related names, comma-joined — null when there is none.
     *
     * @param  Collection<int, Model|null>  $related
     */
    private function summarizeNames(Collection $related): ?string
    {
        $names = $related->filter()->pluck('name')->unique()->values();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    /**
     * Allowed action keys for a single row, via OpportunityPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Handle the `registry`/`referent`/`commercial`/`supervisor`/`source`
     * simple-relation set filters AND the `product_category`/
     * `business_function` AGGREGATED (to-many) set filters, both via
     * `whereHas` on the related row's name (nested dot-path relation for the
     * latter — Eloquent's own `whereHas` support, no raw SQL). Every real
     * column falls through to the generic engine.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        // The `managers` (opportunity_user pivot, to-many) set filter: whereHas
        // on the manager's name, mirroring the simple-relation filters below.
        if ($columnId === 'managers') {
            $values = $this->filterValues($filter);

            if ($values !== []) {
                $query->whereHas('managers', static function (Builder $relatedQuery) use ($values): void {
                    $relatedQuery->whereIn('name', $values);
                });
            }

            return true;
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? self::AGGREGATED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $values = $this->filterValues($filter);

        if ($values !== []) {
            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($values): void {
                $relatedQuery->whereIn('name', $values);
            });
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);
    }

    /**
     * ORDER BY the related row's name via a correlated subquery for every one
     * of the 5 simple-relation derived columns. The 2 AGGREGATED (to-many)
     * columns are NOT sortable (falls through, returns false — no single
     * related row to order by).
     *
     * @param  Builder<Opportunity>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $subquery = DB::table($config['table'])
            ->select('name')
            ->whereColumn("{$config['table']}.id", "opportunities.{$config['fk']}")
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the related row's name for
     * each of the 5 simple-relation derived columns, plus the 2 AGGREGATED
     * (to-many) columns via a join through `opportunity_product_lines` —
     * scoped to the rows matching $query.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === 'managers') {
            return $this->distinctManagerNames($search, $query, $limit);
        }

        if (array_key_exists($columnId, self::AGGREGATED_RELATIONS)) {
            return $this->distinctAggregatedValues(self::AGGREGATED_RELATIONS[$columnId], $search, $query, $limit);
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return null;
        }

        $relatedIds = (clone $query)->whereNotNull($config['fk'])->select($config['fk']);

        return DB::table($config['table'])
            ->whereIn('id', $relatedIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Distinct account-manager names among the opportunities matching $query,
     * via a join through the `opportunity_user` pivot — scoped by every OTHER
     * active filter (Excel-like distinct values, spec 0004/0005).
     *
     * @param  Builder<Opportunity>  $query
     * @return array<int, string>
     */
    private function distinctManagerNames(?string $search, Builder $query, int $limit): array
    {
        $opportunityIds = (clone $query)->select('opportunities.id');

        return DB::table('users')
            ->join('opportunity_user', 'opportunity_user.user_id', '=', 'users.id')
            ->whereIn('opportunity_user.opportunity_id', $opportunityIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('users.name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('users.name')
            ->limit($limit)
            ->pluck('users.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  array{relation: string, table: string, fk: string}  $config
     * @param  Builder<Opportunity>  $query
     * @return array<int, string>
     */
    private function distinctAggregatedValues(array $config, ?string $search, Builder $query, int $limit): array
    {
        $opportunityIds = (clone $query)->select('id');

        return DB::table('opportunity_product_lines')
            ->join($config['table'], "{$config['table']}.id", '=', "opportunity_product_lines.{$config['fk']}")
            ->whereIn('opportunity_product_lines.opportunity_id', $opportunityIds)
            ->when($search !== null && $search !== '', function ($builder) use ($config, $search): void {
                $builder->where("{$config['table']}.name", 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy("{$config['table']}.name")
            ->limit($limit)
            ->pluck("{$config['table']}.name")
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
