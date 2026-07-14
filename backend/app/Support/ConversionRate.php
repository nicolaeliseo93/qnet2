<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Percentage of a part over a total: part / total * 100, rounded to an integer,
 * NULL when total is 0 (never 0% on an empty denominator). Sole consumer is
 * AbstractStatsDefinition::percentStat (registries, companies) since the lead
 * conversion flag was revoked (spec 0026-projects-card-grid, amendment).
 */
final class ConversionRate
{
    public static function of(int $converted, int $total): ?int
    {
        if ($total === 0) {
            return null;
        }

        return (int) round($converted / $total * 100);
    }
}
