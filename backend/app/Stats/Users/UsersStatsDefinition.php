<?php

declare(strict_types=1);

namespace App\Stats\Users;

use App\Models\User;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\Widget;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `users` module (spec 0026): headcount and its
 * active/disabled split, the RBAC breakdown (Spatie `model_has_roles`) and the
 * organizational one (the employment profile's business function). The hiring
 * trend reads `employment_profiles.hired_at` — the only real hire date in the
 * schema; users with no employment profile are simply absent from it.
 */
class UsersStatsDefinition extends AbstractStatsDefinition
{
    private const string EMPLOYMENT_TABLE = 'employment_profiles';

    public function domain(): string
    {
        return 'users';
    }

    public function modelClass(): string
    {
        return User::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();

        return [
            $this->stat('total', $total, icon: 'users'),
            $this->stat(
                key: 'active',
                value: User::query()->where('is_active', true)->count(),
                icon: 'user-check',
            ),
            $this->stat(
                key: 'inactive',
                value: User::query()->where('is_active', false)->count(),
                icon: 'user-x',
            ),
            // `employment_profiles.user_id` is unique, so the flagged profiles
            // ARE the managers (no distinct needed).
            $this->stat(
                key: 'managers',
                value: DB::table(self::EMPLOYMENT_TABLE)->where('is_manager', true)->count(),
                icon: 'briefcase',
            ),
            $this->distribution(
                key: 'by_role',
                items: Aggregates::topRelated(
                    query: $this->roleAssignments(),
                    foreignKey: $this->rolePivotTable().'.role_id',
                    relatedTable: $this->rolesTable(),
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                // Denominator: the role assignments — a user may hold several
                // roles, so each bar is a share of the total assignments.
                total: $this->roleAssignments()->count(),
            ),
            $this->distribution(
                key: 'by_business_function',
                items: Aggregates::topRelated(
                    query: DB::table(self::EMPLOYMENT_TABLE),
                    foreignKey: self::EMPLOYMENT_TABLE.'.business_function_id',
                    relatedTable: 'business_functions',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                total: $total,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(self::EMPLOYMENT_TABLE, 'hired_at', self::TREND_MONTHS),
            ),
        ];
    }

    /**
     * The role assignments of USERS only: `model_has_roles` is polymorphic
     * (morph alias, never the FQCN — see AppServiceProvider::enforceMorphMap).
     */
    private function roleAssignments(): Builder
    {
        return DB::table($this->rolePivotTable())
            ->where($this->rolePivotTable().'.model_type', (new User)->getMorphClass());
    }

    private function rolePivotTable(): string
    {
        return (string) config('permission.table_names.model_has_roles', 'model_has_roles');
    }

    private function rolesTable(): string
    {
        return (string) config('permission.table_names.roles', 'roles');
    }
}
