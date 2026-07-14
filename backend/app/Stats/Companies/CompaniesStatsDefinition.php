<?php

declare(strict_types=1);

namespace App\Stats\Companies;

use App\Models\Company;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\Widget;

/**
 * Statistics panel of the `companies` module (spec 0026): volume and data
 * completeness (VAT number), which is the module's main data-quality signal.
 */
class CompaniesStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'companies';

    public function domain(): string
    {
        return 'companies';
    }

    public function modelClass(): string
    {
        return Company::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();

        return [
            $this->stat('total', $total, icon: 'building'),
            $this->percentStat(
                key: 'with_vat_number',
                count: Company::query()->whereNotNull('vat_number')->count(),
                total: $total,
                icon: 'percent',
            ),
            $this->trend(
                key: 'trend',
                points: Aggregates::monthlyTrend(self::TABLE, 'created_at', self::TREND_MONTHS),
            ),
        ];
    }
}
