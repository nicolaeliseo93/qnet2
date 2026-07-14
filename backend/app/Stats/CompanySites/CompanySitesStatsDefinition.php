<?php

declare(strict_types=1);

namespace App\Stats\CompanySites;

use App\Models\CompanySite;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `company-sites` module (spec 0026): volume, the
 * default-site flag and how the sites spread across companies.
 */
class CompanySitesStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'company_sites';

    public function domain(): string
    {
        return 'company-sites';
    }

    public function modelClass(): string
    {
        return CompanySite::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();

        return [
            $this->stat('total', $total, icon: 'building'),
            $this->stat(
                key: 'default_sites',
                value: CompanySite::query()->where('is_default', true)->count(),
                icon: 'check-circle',
            ),
            $this->distribution(
                key: 'by_company',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE),
                    foreignKey: self::TABLE.'.company_id',
                    relatedTable: 'companies',
                    labelColumn: 'denomination',
                    limit: self::TOP_LIMIT,
                ),
                total: $total,
            ),
        ];
    }
}
