<?php

declare(strict_types=1);

namespace App\Stats\OperationalSites;

use App\Models\OperationalSite;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `operational-sites` module (spec 0026).
 *
 * The site has no own descriptive column (spec 0011: "the site IS its
 * address"), so the geographic breakdown is aggregated on the polymorphic
 * `addresses` rows owned by the site — one per site by the module's invariant
 * (enforced by OperationalSiteService), which is why a plain COUNT(*) on the
 * address rows is a site count.
 */
class OperationalSitesStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'operational_sites';

    private const string ADDRESSES_TABLE = 'addresses';

    public function domain(): string
    {
        return 'operational-sites';
    }

    public function modelClass(): string
    {
        return OperationalSite::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();

        return [
            $this->stat('total', $total, icon: 'map-pin'),
            // The region IS the address' `state` (the geo reference table the
            // module already filters/sorts by — see OperationalSiteGeoColumns).
            $this->distribution(
                key: 'by_region',
                items: Aggregates::topRelated(
                    query: DB::table(self::ADDRESSES_TABLE)
                        ->where(self::ADDRESSES_TABLE.'.addressable_type', (new OperationalSite)->getMorphClass()),
                    foreignKey: self::ADDRESSES_TABLE.'.state_id',
                    relatedTable: 'states',
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
}
