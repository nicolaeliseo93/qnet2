<?php

namespace App\Tables;

use App\Models\BusinessFunction;
use App\Models\User;
use App\Tables\BusinessFunctions\BusinessFunctionColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `business-functions` domain (spec 0010).
 *
 * Real columns (name, is_business_unit, is_business_service, created_at) are
 * handled entirely by the generic engine. `manager` (belongsTo) and `users`
 * (belongsToMany) have no real DB column of their own and are DERIVED: their
 * set filter/sort/distinct-values are resolved here against the related
 * users' names, mirroring UsersTableDefinition's `roles` derived filter.
 */
class BusinessFunctionsTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in the `manager`/`users` set filters.
     * Caps the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    public function domain(): string
    {
        return 'business-functions';
    }

    /**
     * @return class-string<BusinessFunction>
     */
    public function modelClass(): string
    {
        return BusinessFunction::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives BusinessFunctionPolicy::
    // viewAny from modelClass() (business-functions.viewAny).

    /**
     * @return Builder<BusinessFunction>
     */
    public function baseQuery(): Builder
    {
        // Eager-load manager/users + their avatar relation to avoid N+1 when
        // each row projects avatar_url for the manager and every associated user.
        return BusinessFunction::query()->with(['manager.avatar', 'users.avatar']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return BusinessFunctionColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return BusinessFunctionColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return BusinessFunctionColumnCatalog::actions();
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
     * Map a BusinessFunction to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var BusinessFunction $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'is_business_unit' => $row->is_business_unit,
            'is_business_service' => $row->is_business_service,
            'manager' => $this->userSummary($row->manager),
            'users' => $row->users->map(fn (User $user): array => $this->userSummary($user))->all(),
            'created_at' => $row->created_at,
        ];
    }

    /**
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
     * Allowed action keys for a single row, via BusinessFunctionPolicy.
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
     * Handle the derived `manager`/`users` set filters. Every other column id
     * (the real columns) falls through to the generic engine.
     *
     * @param  Builder<BusinessFunction>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return match ($columnId) {
            'manager' => $this->filterByRelatedName($query, 'manager', $filter),
            'users' => $this->filterByRelatedName($query, 'users', $filter),
            default => false,
        };
    }

    /**
     * Derived set filter via whereHas on a user relation (`manager` or
     * `users`), matched by name. Bound parameters, capped cardinality.
     *
     * @param  Builder<BusinessFunction>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterByRelatedName(Builder $query, string $relation, array $filter): bool
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
            $query->whereHas($relation, static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * ORDER BY the manager's name via a correlated subquery, so sorting never
     * needs a row-multiplying JOIN on the main query. `users` (to-many) has no
     * single sort key and is not declared sortable, so it is never reached here.
     *
     * @param  Builder<BusinessFunction>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId !== 'manager') {
            return false;
        }

        $subquery = User::query()
            ->select('name')
            ->whereColumn('users.id', 'business_functions.manager_id')
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the derived `manager`/
     * `users` columns: distinct related-user NAMES among the functions
     * matching `$query` (already scoped by every OTHER active filter).
     *
     * @param  Builder<BusinessFunction>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match ($columnId) {
            'manager' => $this->distinctManagerNames($query, $search, $limit),
            'users' => $this->distinctAssociatedUserNames($query, $search, $limit),
            default => null,
        };
    }

    /**
     * @param  Builder<BusinessFunction>  $query
     * @return array<int, string>
     */
    private function distinctManagerNames(Builder $query, ?string $search, int $limit): array
    {
        $managerIds = (clone $query)->whereNotNull('manager_id')->select('manager_id');

        return DB::table('users')
            ->whereIn('id', $managerIds)
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
     * @param  Builder<BusinessFunction>  $query
     * @return array<int, string>
     */
    private function distinctAssociatedUserNames(Builder $query, ?string $search, int $limit): array
    {
        $functionIds = (clone $query)->select('business_functions.id');

        return DB::table('users')
            ->join('business_function_user', 'business_function_user.user_id', '=', 'users.id')
            ->whereIn('business_function_user.business_function_id', $functionIds)
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
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
