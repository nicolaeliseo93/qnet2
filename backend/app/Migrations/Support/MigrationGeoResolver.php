<?php

namespace App\Migrations\Support;

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;

/**
 * Resolves the external system's geo NAMES (country/region/province/city) to
 * qnet's geo ids (spec 0013 Increment 2 — CompaniesSource/
 * OperationalSitesSource). Own collaborator (does NOT reuse
 * App\Imports\Support\GeoResolver: that belongs to spec 0012's separate,
 * not-yet-committed lane).
 *
 * Mirrors the real hierarchy (country -> state/region -> province -> city,
 * see App\Models\{Country,State,Province,City}): each level is looked up
 * scoped to its resolved parent, by an exact (trimmed) name match — reference
 * data, no fuzzy matching. A level whose name was supplied but does not
 * resolve (unknown name, or its parent itself unresolved) is a non-fatal
 * warning; every level's absence is independent, so partial geo data still
 * resolves whatever it can.
 */
class MigrationGeoResolver
{
    public function resolve(?string $countryName, ?string $stateName, ?string $provinceName, ?string $cityName): MigrationGeoResolution
    {
        $warnings = [];

        $countryId = $this->resolveCountry($countryName, $warnings);
        $stateId = $this->resolveState($countryId, $stateName, $warnings);
        $provinceId = $this->resolveProvince($stateId, $provinceName, $warnings);
        $cityId = $this->resolveCity($stateId, $provinceId, $cityName, $warnings);

        return new MigrationGeoResolution($countryId, $stateId, $provinceId, $cityId, $warnings);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveCountry(?string $name, array &$warnings): ?int
    {
        $name = $this->blankToNull($name);

        if ($name === null) {
            return null;
        }

        $id = Country::query()->where('name', $name)->value('id');

        if ($id === null) {
            $warnings[] = "Unresolved country '{$name}'.";
        }

        return $id;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveState(?int $countryId, ?string $name, array &$warnings): ?int
    {
        $name = $this->blankToNull($name);

        if ($name === null) {
            return null;
        }

        if ($countryId === null) {
            $warnings[] = "Unresolved region '{$name}' (no resolved country).";

            return null;
        }

        $id = State::query()->where('country_id', $countryId)->where('name', $name)->value('id');

        if ($id === null) {
            $warnings[] = "Unresolved region '{$name}'.";
        }

        return $id;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveProvince(?int $stateId, ?string $name, array &$warnings): ?int
    {
        $name = $this->blankToNull($name);

        if ($name === null) {
            return null;
        }

        if ($stateId === null) {
            $warnings[] = "Unresolved province '{$name}' (no resolved region).";

            return null;
        }

        $id = Province::query()->where('state_id', $stateId)->where('name', $name)->value('id');

        if ($id === null) {
            $warnings[] = "Unresolved province '{$name}'.";
        }

        return $id;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveCity(?int $stateId, ?int $provinceId, ?string $name, array &$warnings): ?int
    {
        $name = $this->blankToNull($name);

        if ($name === null) {
            return null;
        }

        if ($stateId === null) {
            $warnings[] = "Unresolved city '{$name}' (no resolved region).";

            return null;
        }

        $id = City::query()
            ->where('state_id', $stateId)
            ->when($provinceId !== null, fn ($query) => $query->where('province_id', $provinceId))
            ->where('name', $name)
            ->value('id');

        if ($id === null) {
            $warnings[] = "Unresolved city '{$name}'.";
        }

        return $id;
    }

    private function blankToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
