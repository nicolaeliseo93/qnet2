<?php

namespace App\Tables;

use App\Enums\GeoScopeLevel;
use App\Models\Project;
use App\Models\User;
use App\Tables\Projects\ProjectColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `projects` domain (spec 0023).
 *
 * Real columns (code, name, start_date, end_date, total_budget, target_lead,
 * created_at) are handled entirely by the generic engine. The 10
 * classification/geo FKs (registry, project_status, source,
 * business_function, country, state, province, city, product_category,
 * partner) have no real column of their own — each is DERIVED against the
 * related row's `name`, resolved here generically via DERIVED_RELATIONS: a
 * `whereHas` set filter (allow-listed columns only, never orderByRaw/
 * whereRaw on raw input — backend.md §8), a correlated subquery sort
 * (registry/project_status only, per the column catalogue) and a
 * `SELECT DISTINCT` for the Excel-like filter list, mirroring
 * ReferentsTableDefinition's `referent_type` / ProductsTableDefinition's
 * `category`. `geo_scope` (spec 0027, D-2) is NOT in DERIVED_RELATIONS: it
 * has no FK/relation of its own (computed from the 4 geo ids), so it is
 * mapped directly in mapRow() and stays outside every filter/sort hook.
 */
class ProjectsTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Allow-list of the 10 classification/geo FKs with no real column of
     * their own: relation accessor, related table and owning FK column,
     * keyed by the derived column id. Single source of truth for
     * applyDerivedFilter/applyDerivedSort/distinctValues below.
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'registry' => ['relation' => 'registry', 'table' => 'registries', 'fk' => 'registry_id'],
        'project_status' => ['relation' => 'projectStatus', 'table' => 'project_statuses', 'fk' => 'project_status_id'],
        'source' => ['relation' => 'source', 'table' => 'sources', 'fk' => 'source_id'],
        'business_function' => ['relation' => 'businessFunction', 'table' => 'business_functions', 'fk' => 'business_function_id'],
        'country' => ['relation' => 'country', 'table' => 'countries', 'fk' => 'country_id'],
        'state' => ['relation' => 'state', 'table' => 'states', 'fk' => 'state_id'],
        'province' => ['relation' => 'province', 'table' => 'provinces', 'fk' => 'province_id'],
        'city' => ['relation' => 'city', 'table' => 'cities', 'fk' => 'city_id'],
        'product_category' => ['relation' => 'productCategory', 'table' => 'product_categories', 'fk' => 'product_category_id'],
        'partner' => ['relation' => 'partner', 'table' => 'referents', 'fk' => 'partner_id'],
    ];

    public function domain(): string
    {
        return 'projects';
    }

    /**
     * @return class-string<Project>
     */
    public function modelClass(): string
    {
        return Project::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives ProjectPolicy::viewAny from
    // modelClass() (projects.viewAny).

    /**
     * @return Builder<Project>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every classification FK to avoid N+1 when each row
        // projects all 7 of them.
        return Project::query()->with(array_column(self::DERIVED_RELATIONS, 'relation'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ProjectColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ProjectColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ProjectColumnCatalog::actions();
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
     * Map a Project to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Project $row */
        $mapped = [
            'id' => $row->id,
            'code' => $row->code,
            'name' => $row->name,
        ];

        foreach (self::DERIVED_RELATIONS as $columnId => $config) {
            $mapped[$columnId] = $this->summarize($row->{$config['relation']});
        }

        $mapped['geo_scope'] = GeoScopeLevel::for($row->country_id, $row->state_id, $row->province_id, $row->city_id)?->value;

        $mapped['start_date'] = $row->start_date;
        $mapped['end_date'] = $row->end_date;
        $mapped['total_budget'] = $row->total_budget;
        $mapped['target_lead'] = $row->target_lead;
        $mapped['created_at'] = $row->created_at;

        return $mapped;
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
     * Allowed action keys for a single row, via ProjectPolicy.
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

        return $allowed;
    }

    /**
     * Handle the 7 derived set filters via whereHas on the related row's
     * name, generically resolved from DERIVED_RELATIONS. Every real column
     * falls through to the generic engine.
     *
     * @param  Builder<Project>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names !== []) {
            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * ORDER BY a derived column's related-row name via a correlated
     * subquery, so sorting never needs a row-multiplying JOIN on the main
     * query. Only `registry`/`project_status` are declared sortable (spec
     * 0023 table_definitions); the other 5 derived columns are never asked
     * to sort (not in sortableColumnIds()).
     *
     * @param  Builder<Project>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $subquery = DB::table($config['table'])
            ->select('name')
            ->whereColumn("{$config['table']}.id", "projects.{$config['fk']}")
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for each of the 7 derived
     * columns: distinct related-row NAMES among the projects matching
     * `$query` (already scoped by every OTHER active filter).
     *
     * @param  Builder<Project>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
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
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
