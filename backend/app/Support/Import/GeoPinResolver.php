<?php

namespace App\Support\Import;

use App\Imports\Recognition\GeoRecognizer;
use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;

/**
 * Turns a validated `geo` PATCH block (spec 0038 — 4 authoritative level ids
 * an operator picked from a cascade select) into the SAME mapped_values keys
 * GeoRecognizer would otherwise fuzzy-resolve from free text: the name
 * fields (country/region/province/city, canonical Model::name; "" when a
 * level is unset) plus the `*_id` fields. UpdateImportRowRequest already
 * validated existence + hierarchy, so a pinned id always resolves to a real
 * row here.
 */
final class GeoPinResolver
{
    /**
     * @param  array{country_id: ?int, state_id: ?int, province_id: ?int, city_id: ?int}  $geo
     * @return array<string, string|int|null>
     */
    public function pin(array $geo): array
    {
        $countryId = $geo['country_id'] ?? null;
        $stateId = $geo['state_id'] ?? null;
        $provinceId = $geo['province_id'] ?? null;
        $cityId = $geo['city_id'] ?? null;

        return [
            GeoRecognizer::COUNTRY_FIELD => $countryId !== null ? (Country::find($countryId)?->name ?? '') : '',
            GeoRecognizer::REGION_FIELD => $stateId !== null ? (State::find($stateId)?->name ?? '') : '',
            GeoRecognizer::PROVINCE_FIELD => $provinceId !== null ? (Province::find($provinceId)?->name ?? '') : '',
            GeoRecognizer::CITY_FIELD => $cityId !== null ? (City::find($cityId)?->name ?? '') : '',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ];
    }
}
