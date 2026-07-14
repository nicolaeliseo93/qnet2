<?php

declare(strict_types=1);

namespace App\Stats\Referents;

use App\Enums\ReferentContactScopeEnum;
use App\Models\Referent;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `referents` module (spec 0026): the internal/
 * external split of the contact base and its type breakdown.
 */
class ReferentsStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'referents';

    public function domain(): string
    {
        return 'referents';
    }

    public function modelClass(): string
    {
        return Referent::class;
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
                key: 'internal',
                value: $this->countByScope(ReferentContactScopeEnum::Internal),
                icon: 'building',
            ),
            $this->stat(
                key: 'external',
                value: $this->countByScope(ReferentContactScopeEnum::External),
                icon: 'target',
            ),
            $this->distribution(
                key: 'by_type',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE),
                    foreignKey: self::TABLE.'.referent_type_id',
                    relatedTable: 'referent_types',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                total: $total,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(self::TABLE, 'created_at', self::TREND_MONTHS),
            ),
        ];
    }

    private function countByScope(ReferentContactScopeEnum $scope): int
    {
        return Referent::query()->where('contact_scope', $scope->value)->count();
    }
}
