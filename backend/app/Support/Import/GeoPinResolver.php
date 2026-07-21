<?php

namespace App\Support\Import;

use App\Imports\Recognition\GeoRecognizer;
use App\Support\Geo\GeoNameResolver;

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
    public function __construct(private readonly GeoNameResolver $geoNames) {}

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

        // An unset level (null id) pins to "" — an authoritative "no value",
        // distinct from GeoRecognizer, which leaves an unresolved level's raw
        // text in place for review.
        $names = $this->geoNames->names($countryId, $stateId, $provinceId, $cityId);

        return [
            GeoRecognizer::COUNTRY_FIELD => $names['country'] ?? '',
            GeoRecognizer::REGION_FIELD => $names['region'] ?? '',
            GeoRecognizer::PROVINCE_FIELD => $names['province'] ?? '',
            GeoRecognizer::CITY_FIELD => $names['city'] ?? '',
            'country_id' => $countryId,
            'state_id' => $stateId,
            'province_id' => $provinceId,
            'city_id' => $cityId,
        ];
    }
}
