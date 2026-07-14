<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Models\City;
use App\Models\Province;
use App\Models\State;

/**
 * BR-4 (spec 0027) parent/child membership checks for the geo hierarchy
 * (Country -> State -> Province -> City): does a given level's row actually
 * belong to the candidate parent it is being paired with. Single source of
 * truth, shared by:
 *   - `ValidatesGeoHierarchy` (FormRequest concern): a violation is a 422 on
 *     the offending field.
 *   - `ProjectService`'s BR-5 realignment cascade (spec 0027 addendum): a
 *     violation left behind by a project update is silently nulled, not
 *     rejected (there is no request to reject).
 * Do not re-derive this rule in either caller.
 */
final class GeoHierarchyMembership
{
    public static function stateBelongsToCountry(int $stateId, ?int $countryId): bool
    {
        return $countryId !== null && State::query()->whereKey($stateId)->where('country_id', $countryId)->exists();
    }

    public static function provinceBelongsToState(int $provinceId, ?int $stateId): bool
    {
        return $stateId !== null && Province::query()->whereKey($provinceId)->where('state_id', $stateId)->exists();
    }

    public static function cityBelongsToState(int $cityId, ?int $stateId): bool
    {
        return $stateId !== null && City::query()->whereKey($cityId)->where('state_id', $stateId)->exists();
    }

    public static function cityBelongsToProvince(int $cityId, ?int $provinceId): bool
    {
        return $provinceId !== null && City::query()->whereKey($cityId)->where('province_id', $provinceId)->exists();
    }
}
