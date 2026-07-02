<?php

namespace App\Tables;

use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

/**
 * Table definition for the `roles` domain.
 *
 * Mirrors UsersTableDefinition: real Role columns (id, name, created_at) plus the
 * derived `permissions` tags column with a `set` filter whose options are the
 * full code-defined permission catalogue. mapRow exposes only safe fields, and
 * actionsFor calls RolePolicy (view→view, update→edit, delete→delete) while
 * hiding edit/delete on the protected `super-admin` system role (affordance ↔
 * the hard guard enforced in RoleService).
 */
class RolesTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of permission names honoured in the `permissions` set
     * filter. Caps the WHERE IN cardinality (defence in depth); excess ignored.
     */
    private const int MAX_PERMISSION_FILTER_VALUES = 100;

    public function domain(): string
    {
        return 'roles';
    }

    /**
     * @return class-string<Role>
     */
    public function modelClass(): string
    {
        return Role::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition derives RolePolicy::viewAny from modelClass()
    // (roles.viewAny).

    /**
     * @return Builder<Role>
     */
    public function baseQuery(): Builder
    {
        // withCount('users') resolves the `users_count` aggregate in a single
        // correlated subquery (no N+1), exposed by mapRow and used as the ORDER BY
        // target when sorting on the derived `users_count` column.
        //
        // The builder's Role is pinned to the canonical `web` guard: Spatie's
        // guard-aware users() relation derives its related model from the role's
        // guard_name, and a blank Role would inherit config('auth.defaults.guard')
        // — which an authenticated API (sanctum) request has mutated to `sanctum`,
        // a guard with no provider model, making users() resolve to null and crash.
        // Mirrors RoleService::guardName().
        $role = new Role(['guard_name' => Guard::getDefaultName(User::class)]);

        return $role->newQuery()->with('permissions')->withCount('users');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            [
                'id' => 'id',
                'label' => 'roles.columns.id',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => false,
                'filterType' => null,
            ],
            [
                'id' => 'name',
                'label' => 'roles.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'permissions',
                'label' => 'roles.columns.permissions',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Number of users currently assigned this role, derived via
                // withCount('users'). Sortable (ORDER BY the users_count alias)
                // and filterable with a number filter (equals/range/comparisons),
                // applied server-side as a count comparison on the users relation
                // (see applyUsersCountFilter). Rendered as a badge on the frontend.
                'id' => 'users_count',
                'label' => 'roles.columns.users_count',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'created_at',
                'label' => 'roles.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'permissions', 'type' => 'set'],
            ['columnId' => 'users_count', 'type' => 'number'],
            ['columnId' => 'created_at', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return [
            [
                'key' => 'view',
                'label' => 'actions.view',
                'icon' => 'eye',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'roles.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'roles.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'roles.delete',
            ],
        ];
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
     * Dynamic `permissions` options: the full code-defined permission catalogue,
     * used both by the set filter and (read by the thin frontend adapter from the
     * same config) to render the permission checkboxes in the role form. Single
     * source of truth — the frontend never hardcodes permission names.
     *
     * @return array<int, scalar>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        if ($columnId === 'permissions') {
            /** @var array<int, string> $names */
            $names = Permission::query()->orderBy('name')->pluck('name')->all();

            return $names;
        }

        return null;
    }

    /**
     * Map a Role model to the row payload (real fields + derived permissions).
     * `actions` is attached by the generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Role $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'permissions' => $row->getPermissionNames()->all(),
            // Aggregate resolved by withCount('users') in baseQuery; cast to int
            // (the driver may return it as a string). 0 when no user has the role.
            'users_count' => (int) ($row->users_count ?? 0),
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, computed via RolePolicy. The
     * protected `super-admin` system role never offers edit/delete (it cannot be
     * mutated — RoleService enforces the hard guard), so the UI never advertises
     * a dead-end action.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var Role $row */
        $isSystemRole = $row->name === UserService::PRIVILEGED_ROLE;

        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (! $isSystemRole && Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (! $isSystemRole && Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        return $allowed;
    }

    /**
     * Handle the roles domain's derived columns (no real DB column of their own):
     * the `permissions` set filter and the `users_count` aggregate filter. Every
     * other column id falls through to the generic real-column path.
     *
     * @param  Builder<Role>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return match ($columnId) {
            'permissions' => $this->filterPermissions($query, $filter),
            'users_count' => $this->filterUsersCount($query, $filter),
            default => false,
        };
    }

    /**
     * Derived `permissions` set filter via whereHas on the Spatie permission
     * relationship. Only string names, capped cardinality, bound parameters —
     * names are never inlined into SQL.
     *
     * @param  Builder<Role>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterPermissions(Builder $query, array $filter): bool
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values) || $values === []) {
            return true;
        }

        $permissions = array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        ));

        if ($permissions === []) {
            return true;
        }

        // Cap cardinality so the WHERE IN stays bounded (defence in depth).
        $permissions = array_slice($permissions, 0, self::MAX_PERMISSION_FILTER_VALUES);

        $query->whereHas('permissions', static function (Builder $permissionQuery) use ($permissions): void {
            $permissionQuery->whereIn('name', $permissions);
        });

        return true;
    }

    /**
     * Derived `users_count` number filter. The count is an aggregate (no real DB
     * column), so it is applied as a count comparison on the `users` relation via
     * has() — a bound correlated-subquery WHERE that is portable across drivers
     * (no HAVING/alias dependency) and respected by the (clone)->count() total.
     *
     * The relation resolves off the guard-pinned model from baseQuery(), so the
     * sanctum default-guard pitfall (see baseQuery) does not apply here either.
     * AG Grid number operators map to SQL comparisons; `inRange` becomes a closed
     * interval. Non-numeric / blank payloads add no constraint.
     *
     * @param  Builder<Role>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterUsersCount(Builder $query, array $filter): bool
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';

        if ($type === 'inRange') {
            $from = $this->intOrNull($filter['filter'] ?? null);
            $to = $this->intOrNull($filter['filterTo'] ?? null);

            if ($from !== null) {
                $query->has('users', '>=', $from);
            }

            if ($to !== null) {
                $query->has('users', '<=', $to);
            }

            return true;
        }

        $value = $this->intOrNull($filter['filter'] ?? null);

        if ($value === null) {
            return true; // blank / notBlank / malformed → no constraint
        }

        $operator = match ($type) {
            'notEqual' => '!=',
            'lessThan' => '<',
            'lessThanOrEqual' => '<=',
            'greaterThan' => '>',
            'greaterThanOrEqual' => '>=',
            default => '=', // 'equals'
        };

        $query->has('users', $operator, $value);

        return true;
    }

    /**
     * Coerce a filter payload value to a non-negative int, or null when it is not
     * a usable numeric value (so the filter adds no constraint).
     */
    private function intOrNull(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }
}
