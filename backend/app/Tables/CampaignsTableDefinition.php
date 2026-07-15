<?php

namespace App\Tables;

use App\Enums\GeoScopeLevel;
use App\Models\Campaign;
use App\Models\Project;
use App\Models\User;
use App\Tables\Campaigns\CampaignAdvancedFilterCatalog;
use App\Tables\Campaigns\CampaignColumnCatalog;
use App\Tables\Campaigns\CampaignPipelineStatusResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `campaigns` domain (spec 0023).
 *
 * Real columns (code, name, start_date, end_date, total_budget, target_lead,
 * created_at) are handled entirely by the generic engine. `project`,
 * `registry` and `source` are simple relation-name derived columns (own FK on
 * the campaign), resolved generically via DERIVED_RELATIONS — a `whereHas`
 * set filter (allow-listed columns only, never orderByRaw/whereRaw on raw
 * input — backend.md §8) and, for `project` only (the sole sortable one), a
 * correlated subquery sort, mirroring ProjectsTableDefinition.
 *
 * `pipeline_status` is the ONE doubly-derived column (BR-2/AC-032: a linked
 * campaign's OWN pipeline_status_id is NULL, its effective status is read
 * through the project) — delegated to CampaignPipelineStatusResolver (file-
 * size split) rather than a SQL-level JOIN/COALESCE on the base query (which
 * would risk ambiguous column names: `campaigns` and `projects` share
 * several). It is filterable-only (never sortable — spec 0023
 * table_definitions).
 */
class CampaignsTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * The doubly-derived `pipeline_status` column id (AC-032), delegated to
     * CampaignPipelineStatusResolver rather than the DERIVED_RELATIONS map.
     */
    private const string PROJECT_STATUS_COLUMN = 'pipeline_status';

    /**
     * Simple (single-hop) relation-name derived columns: relation accessor,
     * related table and owning FK column, keyed by the derived column id.
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'project' => ['relation' => 'project', 'table' => 'projects', 'fk' => 'project_id'],
        'registry' => ['relation' => 'registry', 'table' => 'registries', 'fk' => 'registry_id'],
        'source' => ['relation' => 'source', 'table' => 'sources', 'fk' => 'source_id'],
    ];

    public function __construct(private readonly CampaignPipelineStatusResolver $pipelineStatusResolver) {}

    public function domain(): string
    {
        return 'campaigns';
    }

    /**
     * @return class-string<Campaign>
     */
    public function modelClass(): string
    {
        return Campaign::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives CampaignPolicy::viewAny
    // from modelClass() (campaigns.viewAny).

    /**
     * @return Builder<Campaign>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every relation mapRow/effectiveStatus touches to avoid
        // N+1 across the page. `project.{country,state,province,city}` plus
        // the campaign's own geo (spec 0027, BR-5) feed the merged display
        // columns below.
        return Campaign::query()->with([
            'project.pipelineStatus',
            'project.country',
            'project.state',
            'project.province',
            'project.city',
            'registry',
            'source',
            'pipelineStatus',
            'country',
            'state',
            'province',
            'city',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return CampaignColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return CampaignColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return CampaignColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return CampaignAdvancedFilterCatalog::advancedFilters();
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
     * Map a Campaign to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Campaign $row */
        $project = $row->project;
        $country = $row->country ?? $project?->country;
        $state = $row->state ?? $project?->state;
        $province = $row->province ?? $project?->province;
        $city = $row->city ?? $project?->city;

        return [
            'id' => $row->id,
            'code' => $row->code,
            'project' => $this->summarizeProject($project),
            'name' => $row->name,
            'registry' => $this->summarize($row->registry),
            'pipeline_status' => $this->summarize($this->pipelineStatusResolver->effectiveStatus($row)),
            'source' => $this->summarize($row->source),
            'country' => $this->summarize($country),
            'state' => $this->summarize($state),
            'province' => $this->summarize($province),
            'city' => $this->summarize($city),
            'geo_scope' => GeoScopeLevel::for($country?->id, $state?->id, $province?->id, $city?->id)?->value,
            'start_date' => $row->start_date,
            'end_date' => $row->end_date,
            'total_budget' => $row->total_budget,
            'target_lead' => $row->target_lead,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeProject(?Model $project): ?array
    {
        if ($project === null) {
            return null;
        }

        /** @var Project $project */
        return ['id' => $project->id, 'name' => sprintf('%s — %s', $project->code, $project->name)];
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
     * Allowed action keys for a single row, via CampaignPolicy.
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
     * `pipeline_status` (BR-2/AC-032) has no relation-by-id equivalent through
     * the generic default: a linked campaign's OWN status is NULL, so it must
     * match the campaign's own status OR its linked project's — delegated to
     * CampaignPipelineStatusResolver::applyIdFilter(). Every other advanced
     * filter declared in CampaignAdvancedFilterCatalog (`project`/`registry`/
     * `source`/`partner` relation-by-id, `budget_range`/`created_range`
     * direct-column) is handled by the generic default.
     *
     * @param  Builder<Campaign>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        if ($name === self::PROJECT_STATUS_COLUMN) {
            if (is_array($value)) {
                $this->pipelineStatusResolver->applyIdFilter($query, $value);
            } elseif (is_scalar($value) && $value !== '') {
                $this->pipelineStatusResolver->applyIdFilter($query, [$value]);
            }

            return true;
        }

        return parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * Handle the `project`/`registry`/`source` set filters via whereHas on
     * the related row's name; `pipeline_status` is delegated to
     * CampaignPipelineStatusResolver (AC-032). Every real column falls
     * through to the generic engine.
     *
     * @param  Builder<Campaign>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $names = $this->filterNames($filter);

        if ($columnId === self::PROJECT_STATUS_COLUMN) {
            $this->pipelineStatusResolver->applyFilter($query, $names);

            return true;
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        if ($names !== []) {
            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterNames(array $filter): array
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
     * ORDER BY the linked project's name via a correlated subquery — only
     * `project` is declared sortable (spec 0023 table_definitions);
     * `registry`/`pipeline_status`/`source` are never asked to sort (not in
     * sortableColumnIds()).
     *
     * @param  Builder<Campaign>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null || $columnId !== 'project') {
            return false;
        }

        $subquery = DB::table($config['table'])
            ->select('name')
            ->whereColumn("{$config['table']}.id", "campaigns.{$config['fk']}")
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005). `project`/`registry`/
     * `source` are plain related-row names; `pipeline_status` is delegated to
     * CampaignPipelineStatusResolver (AC-032).
     *
     * @param  Builder<Campaign>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === self::PROJECT_STATUS_COLUMN) {
            return $this->pipelineStatusResolver->distinctValues($search, $query, $limit);
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
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
