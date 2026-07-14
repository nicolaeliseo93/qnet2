<?php

declare(strict_types=1);

namespace App\Stats\Leads;

use App\Models\Lead;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `leads` module (spec 0026): volume, conversion and
 * the two breakdowns the commercial team works by (source, operator).
 */
class LeadsStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'leads';

    public function domain(): string
    {
        return 'leads';
    }

    public function modelClass(): string
    {
        return Lead::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();
        $converted = Lead::query()->where('is_converted', true)->count();

        return [
            $this->stat('total', $total, icon: 'users'),
            $this->stat('converted', $converted, icon: 'user-check'),
            $this->percentStat('conversion_rate', $converted, $total, icon: 'percent'),
            $this->distribution(
                key: 'by_source',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE),
                    foreignKey: self::TABLE.'.source_id',
                    relatedTable: 'sources',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                total: $total,
            ),
            $this->distribution(
                key: 'by_operator',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE),
                    foreignKey: self::TABLE.'.operator_id',
                    relatedTable: 'users',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                total: $total,
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(self::TABLE, 'created_at', self::TREND_MONTHS),
                format: StatFormat::Number,
            ),
        ];
    }
}
