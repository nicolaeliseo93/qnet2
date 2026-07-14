<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The derived geographic scope of a project/campaign (spec 0027, D-2): the
 * FINEST non-null level among country/state/province/city, or null when none
 * is set. NEVER persisted — computed on the fly and emitted by the resources
 * as `geo_scope`. Single source of truth for this rule: do not re-derive it
 * anywhere else.
 */
enum GeoScopeLevel: string
{
    case Country = 'country';
    case State = 'state';
    case Province = 'province';
    case City = 'city';

    /**
     * The finest non-null level among the four ids, or null when no geo at
     * all is set.
     */
    public static function for(?int $countryId, ?int $stateId, ?int $provinceId, ?int $cityId): ?self
    {
        return match (true) {
            $cityId !== null => self::City,
            $provinceId !== null => self::Province,
            $stateId !== null => self::State,
            $countryId !== null => self::Country,
            default => null,
        };
    }
}
