<?php

namespace App\Support\Geo;

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;

/**
 * Maps a resolved set of geo level ids back to their canonical reference
 * names (Country/State/Province/City::name). A null id yields a null name.
 *
 * Shared by every import path that needs the id -> name direction: the CSV
 * recognizer (App\Imports\Recognition\GeoRecognizer, which backfills the
 * ancestor NAME columns once matching pins their ids) and the operator's
 * cascade pin (App\Support\Import\GeoPinResolver). One place, so the two
 * never drift.
 */
final class GeoNameResolver
{
    /**
     * @return array{country: ?string, region: ?string, province: ?string, city: ?string}
     */
    public function names(?int $countryId, ?int $stateId, ?int $provinceId, ?int $cityId): array
    {
        return [
            'country' => $countryId !== null ? Country::find($countryId)?->name : null,
            'region' => $stateId !== null ? State::find($stateId)?->name : null,
            'province' => $provinceId !== null ? Province::find($provinceId)?->name : null,
            'city' => $cityId !== null ? City::find($cityId)?->name : null,
        ];
    }
}
