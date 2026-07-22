<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\Opportunity;
use App\Models\User;
use App\Tables\RequestManagement\RequestAdvancedFilterCatalog;
use App\Tables\RequestManagement\RequestColumnCatalog;
use App\Tables\RequestManagement\RequestRowMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Table definition for the `request-management` domain (spec 0049): the
 * "Gestione Richieste" operative view over `opportunities` (D-1, no new
 * entity, no new controller/route — ADR 0002). Access runs through the
 * module's OWN dedicated permission set (`request-management.*`, D-2), never
 * `opportunities.*` — `modelClass()` is Opportunity ONLY because that is
 * what the row IS, not because its Policy governs this domain.
 *
 * Two deviations from a plain relation-derived definition:
 *  - `authorizeViewAny()` is OVERRIDDEN: the AbstractTableDefinition fail-safe
 *    default would resolve `Gate::allows('viewAny', Opportunity::class)` →
 *    OpportunityPolicy → `opportunities.viewAny`, the WRONG permission for
 *    this domain. A direct `request-management.viewAny` permission check
 *    replaces it (fail-closed: no permission registered → false, never
 *    fail-open).
 *  - `baseQuery()` scopes to the actor's own managed opportunities (D-3,
 *    `opportunity_user` pivot) UNLESS they hold `request-management.viewAll`,
 *    mirroring LeadImportsTableDefinition's `Auth::id()` scoping precedent
 *    (authorizeViewAny runs first; a null id simply matches no rows via
 *    `whereHas`, never fail-open).
 */
class RequestManagementTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * The `workflow_status` advanced filter's name (RequestAdvancedFilterCatalog):
     * a SET filter matched by the related row's `name`, not by id — no
     * `opportunity-workflow-statuses/for-select` route exists to back an
     * id-based Relation/AsyncSearch picker.
     */
    private const string WORKFLOW_STATUS_ADVANCED_FILTER = 'workflow_status';

    /**
     * Simple (single-hop) relation-name derived columns: relation accessor,
     * related table and owning FK column, keyed by the derived column id —
     * mirrors OpportunitiesTableDefinition::DERIVED_RELATIONS, restricted to
     * the 4 columns this operative list exposes.
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'workflow_status' => ['relation' => 'workflowStatus', 'table' => 'opportunity_workflow_statuses', 'fk' => 'opportunity_workflow_status_id'],
    ];

    /**
     * The single AGGREGATED (to-many, via `productLines`) derived column:
     * filterable but never sortable (amendment rev.3 pattern, no single
     * related row to order by).
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array AGGREGATED_RELATIONS = [
        'product_categories' => ['relation' => 'productLines.productCategory', 'table' => 'product_categories', 'fk' => 'product_category_id'],
    ];

    public function __construct(private readonly RequestRowMapper $rowMapper) {}

    public function domain(): string
    {
        return 'request-management';
    }

    /**
     * @return class-string<Opportunity>
     */
    public function modelClass(): string
    {
        return Opportunity::class;
    }

    /**
     * Dedicated permission check (see class docblock): NEVER delegates to
     * OpportunityPolicy — `request-management` is a separate permission set
     * (D-2), independent of `opportunities.viewAny`.
     */
    public function authorizeViewAny(User $actor): bool
    {
        return $actor->can('request-management.viewAny');
    }

    /**
     * @return Builder<Opportunity>
     */
    public function baseQuery(): Builder
    {
        $query = Opportunity::query()->with([
            'workflowStatus', 'productLines.productCategory',
            // `managers.avatar` so the "Operatore" (GA2) column can project the
            // inline avatar for the shared UserCell without a per-row query.
            'managers.avatar',
            // The client anagraphic columns (nome/cognome/codice fiscale/telefono)
            // are read from the Registry's PersonalData card + its primary
            // contacts, all resolved from memory in mapRow (no N+1).
            'registry.personalData.contacts',
        ])
            // Per-row count for the `documents` action badge (HasAttachments),
            // scoped to the 'documents' collection only — never other
            // collections (mirrors OpportunitiesTableDefinition).
            ->withCount(['attachments as documents_count' => fn (Builder $q) => $q->where('collection', 'documents')])
            // Per-row count for the `notes` action badge (spec 0052 B4c,
            // HasNotes): roots AND replies together (the whole discussion),
            // soft-deleted notes excluded automatically by Note's own
            // SoftDeletes global scope — a single aggregated query, no N+1.
            ->withCount('notes');

        // D-3 scoping: only the opportunities where the actor is the GA2
        // "Operatore" (pivot position 2), unless they hold the viewAll ability.
        // `Auth::id()` is always set here (authorizeViewAny runs first and
        // requires auth); a null id would simply match no rows (never
        // fail-open) — mirrors LeadImportsTableDefinition's docblock reasoning.
        if (! Auth::user()?->can('request-management.viewAll')) {
            $query->whereHas('managers', static function (Builder $relatedQuery): void {
                $relatedQuery->where('users.id', Auth::id())
                    ->where('opportunity_user.position', Opportunity::OPERATOR_MANAGER_POSITION);
            });
        }

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return RequestColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return RequestColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return RequestColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return RequestAdvancedFilterCatalog::advancedFilters();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'updated_at', 'direction' => 'desc'],
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
     * Map an Opportunity to the operative row payload (delegated to
     * RequestRowMapper, so this definition keeps a single concern: query
     * building). `actions` is attached by the generic TableService via
     * actionsFor(); `documents_count`/`notes_count` ride along from
     * baseQuery's withCount.
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Opportunity $row */
        return [
            ...$this->rowMapper->map($row),
            'documents_count' => (int) ($row->documents_count ?? 0),
            'notes_count' => (int) ($row->notes_count ?? 0),
        ];
    }

    /**
     * `view` ("Lavora"), `notes` (spec 0052 B4b) and `documents` — both `view`
     * and `notes` gated by the SAME `request-management.view` (D-6: reading a
     * record's notes is inherited from the ability to open the record, no
     * separate notes permission), never OpportunityPolicy:
     * `Gate::allows('view', $row)` would resolve OpportunityPolicy
     * (`opportunities.view`), the wrong permission for this domain. No
     * `activity` action: the module exposes no separately-gated activity
     * surface (see RequestColumnCatalog::actions()).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if ($actor->can('request-management.view')) {
            $allowed[] = 'view';
            $allowed[] = 'notes';
        }

        if ($actor->can('request-management.viewDocuments')) {
            $allowed[] = 'documents';
        }

        return $allowed;
    }

    /**
     * Handle the 4 simple-relation set filters AND the `product_categories`
     * AGGREGATED (to-many) set filter, both via `whereHas` on the related
     * row's name (allow-listed columns only, never orderByRaw/whereRaw on raw
     * input — backend.md §8). Every real column falls through to the generic
     * engine.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? self::AGGREGATED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $values = $this->filterValues($filter);

        if ($values !== []) {
            $this->applyNameWhereHas($query, $config['relation'], $values);
        }

        return true;
    }

    /**
     * `workflow_status`'s advanced filter (RequestAdvancedFilterCatalog) is a
     * SET filter matched by the related row's `name` (see the constant's
     * docblock) — the generic default (a plain `whereHas`-by-id for `type:
     * relation`/`async_search`) cannot express it. Every other advanced
     * filter declared in the catalog is a standard relation-by-id or real
     * column, handled by the generic default.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        if ($name === self::WORKFLOW_STATUS_ADVANCED_FILTER) {
            $values = array_slice(array_values(array_filter(
                is_array($value) ? $value : [$value],
                static fn (mixed $item): bool => is_string($item) && $item !== '',
            )), 0, self::MAX_FILTER_VALUES);

            if ($values !== []) {
                $this->applyNameWhereHas($query, 'workflowStatus', $values);
            }

            return true;
        }

        return parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * `whereHas` on a relation's own `name`, bound and never raw — shared by
     * the `workflow_status` derived-column set filter (applyDerivedFilter)
     * and its advanced-filter twin (applyAdvancedFilter above).
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<int, string>  $values
     */
    private function applyNameWhereHas(Builder $query, string $relation, array $values): void
    {
        $query->whereHas($relation, static function (Builder $relatedQuery) use ($values): void {
            $relatedQuery->whereIn('name', $values);
        });
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
     * of the 4 simple-relation derived columns. `product_categories` (the
     * AGGREGATED to-many column) is NOT sortable (falls through, returns
     * false — no single related row to order by).
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
     * each of the 4 simple-relation derived columns, plus
     * `product_categories` via a join through `opportunity_product_lines` —
     * scoped to the rows matching $query.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
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
