<?php

declare(strict_types=1);

namespace App\Support;

/**
 * BR-1 (spec 0026): conversion_rate = converted / total * 100, rounded to an
 * integer, NULL when total is 0 (never 0% on an empty denominator). Shared
 * between ProjectCardResource (per-card) and ProjectService::summary()
 * (global) so the rule lives in exactly one place.
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
