<?php

namespace App\Tables;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Tables\Leads\LeadAdvancedFilterCatalog;
use App\Tables\Leads\LeadColumnCatalog;
use App\Tables\Leads\LeadOperationalSiteColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `leads` domain (spec 0024).
 *
 * `created_at` is the only real column handled entirely by the generic
 * engine. `referent`/`campaign`/`source`/`operator` are simple relation-name
 * derived columns (own FK on the lead), resolved generically via
 * DERIVED_RELATIONS — a `whereHas` set filter (allow-listed columns only,
 * never orderByRaw/whereRaw on raw input — backend.md §8) and a correlated
 * subquery sort, mirroring CampaignsTableDefinition. `operational_site` is
 * the ONE specially-derived column (BR-3: no own name, sort/filter/distinct
 * pass through the site's primary address `line1`) — delegated to
 * LeadOperationalSiteColumn (file-size split).
 */
class LeadsTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    private const string OPERATIONAL_SITE_COLUMN = 'operational_site';

    /**
     * Derived boolean column: whether an operator is assigned to the lead
     * (`operator_id IS NOT NULL`). No real DB column, so filter and sort are
     * resolved here against the FK.
     */
    private const string IS_ASSIGNED_COLUMN = 'is_assigned';

    private const string OPERATOR_FK = 'operator_id';

    /**
     * Simple (single-hop) relation-name derived columns: relation accessor,
     * related table and owning FK column, keyed by the derived column id.
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'referent' => ['relation' => 'referent', 'table' => 'referents', 'fk' => 'referent_id'],
        'campaign' => ['relation' => 'campaign', 'table' => 'campaigns', 'fk' => 'campaign_id'],
        'source' => ['relation' => 'source', 'table' => 'sources', 'fk' => 'source_id'],
        'operator' => ['relation' => 'operator', 'table' => 'users', 'fk' => 'operator_id'],
        'lead_status' => ['relation' => 'leadStatus', 'table' => 'lead_statuses', 'fk' => 'lead_status_id'],
    ];

    public function __construct(private readonly LeadOperationalSiteColumn $operationalSiteColumn) {}

    public function domain(): string
    {
        return 'leads';
    }

    /**
     * @return class-string<Lead>
     */
    public function modelClass(): string
    {
        return Lead::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives LeadPolicy::viewAny from
    // modelClass() (leads.viewAny).

    /**
     * @return Builder<Lead>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every relation mapRow touches to avoid N+1 across the
        // page (operationalSite's address+city for the composed label, BR-3).
        return Lead::query()->with(['referent', 'campaign', 'operationalSite.addresses.city', 'source', 'operator', 'leadStatus']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return LeadColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return LeadColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return LeadColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return LeadAdvancedFilterCatalog::advancedFilters();
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
     * Map a Lead to the row payload. `notes` is emitted even though it is not
     * a declared (sortable/filterable) column, matching the data contract's
     * row shape. `actions` is attached by the generic TableService via
     * actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Lead $row */
        return [
            'id' => $row->id,
            'referent' => $this->summarize($row->referent),
            'campaign' => $this->summarize($row->campaign),
            'operational_site' => $this->operationalSiteColumn->summarize($row),
            'source' => $this->summarize($row->source),
            'operator' => $this->summarize($row->operator),
            'is_assigned' => $row->operator_id !== null,
            'lead_status' => $this->summarizeLeadStatus($row->leadStatus),
            'notes' => $row->notes,
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
     * `lead_status_id` is NOT NULL (spec 0029 D-1). Mapped EXPLICITLY with
     * `color`, never via summarize(): reusing it here would reproduce the
     * scolored badge defect ProjectsTableDefinition::mapRow() has via
     * summarize() on `pipeline_status` (spec 0029 context/known_defect_not_ours,
     * out of scope to fix there).
     *
     * @return array{id: int, name: string, color: ?string}|null
     */
    private function summarizeLeadStatus(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        /** @var LeadStatus $related */
        return ['id' => $related->id, 'name' => $related->name, 'color' => $related->color];
    }

    /**
     * Allowed action keys for a single row, via LeadPolicy.
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
     * `operational_site` (BR-3, spec 0032) has no relation-by-id equivalent —
     * the site carries no own name — so the generic default (which delegates
     * a `relation` type to a plain whereHas-by-id) cannot express it. Every
     * other advanced filter declared in LeadAdvancedFilterCatalog is a
     * standard relation-by-id, handled by the generic default.
     *
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        if ($name === self::OPERATIONAL_SITE_COLUMN) {
            if (is_string($value) && $value !== '') {
                $this->operationalSiteColumn->applyAdvancedFilter($query, $value);
            }

            return true;
        }

        return parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * Handle the `referent`/`campaign`/`source`/`operator` set filters via
     * whereHas on the related row's name; `operational_site` is delegated to
     * LeadOperationalSiteColumn (BR-3). Every real column (created_at) falls
     * through to the generic engine.
     *
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $values = $this->filterValues($filter);

        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applyFilter($query, $values);

            return true;
        }

        if ($columnId === self::IS_ASSIGNED_COLUMN) {
            $this->applyAssignedFilter($query, $values);

            return true;
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        if ($values !== []) {
            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($values): void {
                $relatedQuery->whereIn('name', $values);
            });
        }

        return true;
    }

    /**
     * `is_assigned` set filter: '1' keeps leads with an operator, '0' keeps
     * unassigned ones; both (or neither) selected leaves the query untouched.
     *
     * @param  Builder<Lead>  $query
     * @param  array<int, string>  $values
     */
    private function applyAssignedFilter(Builder $query, array $values): void
    {
        $wantsAssigned = in_array('1', $values, true);
        $wantsUnassigned = in_array('0', $values, true);

        if ($wantsAssigned === $wantsUnassigned) {
            return;
        }

        $wantsAssigned
            ? $query->whereNotNull(self::OPERATOR_FK)
            : $query->whereNull(self::OPERATOR_FK);
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
     * ORDER BY the related row's name via a correlated subquery for the 4
     * standard relational columns; `operational_site` (BR-3) is delegated to
     * LeadOperationalSiteColumn.
     *
     * @param  Builder<Lead>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applySort($query, $direction);

            return true;
        }

        if ($columnId === self::IS_ASSIGNED_COLUMN) {
            // Boolean semantics via the FK: NULL (unassigned) sorts before any
            // id on ASC in both MySQL and SQLite, i.e. false < true. No raw SQL.
            $query->orderBy(self::OPERATOR_FK, $direction);

            return true;
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $subquery = DB::table($config['table'])
            ->select('name')
            ->whereColumn("{$config['table']}.id", "leads.{$config['fk']}")
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005). `referent`/`campaign`/
     * `source`/`operator` are plain related-row names; `operational_site`
     * (BR-3) is delegated to LeadOperationalSiteColumn.
     *
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            return $this->operationalSiteColumn->distinctValues($search, $query, $limit);
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
