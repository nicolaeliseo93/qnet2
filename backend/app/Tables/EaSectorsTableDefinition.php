<?php

namespace App\Tables;

use App\Models\EaSector;
use App\Models\User;
use App\Services\EaSectorService;
use App\Tables\EaSectors\EaSectorColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `ea-sectors` domain (spec 0018): the AG Grid
 * SSRM list view, alongside the dedicated tree view (GET /ea-sectors/tree,
 * used by the future parent picker).
 *
 * Real columns (name, created_at) are handled entirely by the generic
 * engine. `parent` is DERIVED (no real DB column — the related parent's
 * name), mirroring ProductCategoriesTableDefinition's `parent` column.
 */
class EaSectorsTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in the `parent` set filter. Caps the
     * WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(private readonly EaSectorService $service) {}

    public function domain(): string
    {
        return 'ea-sectors';
    }

    /**
     * @return class-string<EaSector>
     */
    public function modelClass(): string
    {
        return EaSector::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives EaSectorPolicy::viewAny
    // from modelClass() (ea-sectors.viewAny).

    /**
     * @return Builder<EaSector>
     */
    public function baseQuery(): Builder
    {
        // Eager-load parent (no N+1 across the page's rows).
        return EaSector::query()->with(['parent']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return EaSectorColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return EaSectorColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return EaSectorColumnCatalog::actions();
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
     * Map an EaSector to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var EaSector $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'parent' => $this->parentSummary($row->parent),
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function parentSummary(?EaSector $parent): ?array
    {
        if ($parent === null) {
            return null;
        }

        return ['id' => $parent->id, 'name' => $parent->name];
    }

    /**
     * Allowed action keys for a single row, via EaSectorPolicy.
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
     * Handle the derived `parent` set filter. Every other column id (the
     * real columns) falls through to the generic engine.
     *
     * @param  Builder<EaSector>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId !== 'parent') {
            return false;
        }

        return $this->filterByParentName($query, $filter);
    }

    /**
     * Derived set filter via whereHas on the self-referencing `parent`
     * relation, matched by name. Bound parameters, capped cardinality.
     *
     * @param  Builder<EaSector>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterByParentName(Builder $query, array $filter): bool
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names !== []) {
            $query->whereHas('parent', static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * ORDER BY the parent's name via a correlated subquery, so sorting never
     * needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<EaSector>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'parent') {
            return false;
        }

        // Self-join: the subquery's own table is aliased (`parent_sector`) so
        // it never collides with the outer query's `ea_sectors`.
        $subquery = EaSector::query()
            ->from('ea_sectors as parent_sector')
            ->select('parent_sector.name')
            ->whereColumn('parent_sector.id', 'ea_sectors.parent_id')
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the derived `parent`
     * column.
     *
     * @param  Builder<EaSector>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId !== 'parent') {
            return null;
        }

        return $this->distinctParentNames($query, $search, $limit);
    }

    /**
     * @param  Builder<EaSector>  $query
     * @return array<int, string>
     */
    private function distinctParentNames(Builder $query, ?string $search, int $limit): array
    {
        $parentIds = (clone $query)->whereNotNull('parent_id')->select('parent_id');

        return DB::table('ea_sectors')
            ->whereIn('id', $parentIds)
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

    /**
     * Delegate to EaSectorService::delete() so the generic bulk-delete
     * endpoint respects the exact same restrictive-delete guard (children)
     * as the single DELETE /ea-sectors/{eaSector} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var EaSector $model */
        $this->service->delete($model);
    }
}
