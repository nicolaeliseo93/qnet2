<?php

namespace App\Imports\Support;

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Support\Geo\ItalianGeoLocalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Name -> id geo resolution for the CSV import engine (spec 0012): the CSV
 * carries human-readable geo NAMES (country/region/province/city), never ids,
 * so every address-bearing ImportDefinition resolves them through here before
 * building its domain DTO (App\DataObjects\PersonalData\CreateAddress).
 *
 * Every level is first passed through ItalianGeoLocalizer (shared with the
 * migration resolver), so the Italian strings imports carry — `Italia`,
 * `Sicilia`, the province plate code `NA`, `Napoli`, and label-noisy comuni —
 * match the ENGLISH reference dataset. A non-Italian / already-correct value
 * passes through unchanged, so this stays a general resolver, not IT-only.
 *
 * Resolution is hierarchical and case-insensitive: a city is disambiguated
 * WITHIN the given province, a province WITHIN the given state, a state
 * WITHIN the given country — so a homonym city in another province (or a
 * homonym province in another state) never matches. Only a NON-EMPTY name
 * that fails to resolve (not found, or ambiguous within its scope) is an
 * error; a blank/absent name at any level simply yields no id, no error.
 *
 * Matching filters candidates via Eloquent (parent-scoped, real columns only)
 * and compares names in PHP — no raw SQL, so behavior is identical regardless
 * of the underlying database's collation (MySQL in production, SQLite in
 * tests).
 */
class GeoResolver
{
    public function __construct(private readonly ItalianGeoLocalizer $localizer) {}

    public function resolve(
        ?string $countryName,
        ?string $stateName,
        ?string $provinceName,
        ?string $cityName,
    ): GeoResolutionResult {
        $countryId = null;
        $stateId = null;
        $provinceId = null;
        $cityId = null;

        if ($this->present($countryName)) {
            $country = $this->findByName(Country::query(), $this->localizer->country($countryName));

            if ($country === null) {
                return GeoResolutionResult::failed("Country \"{$countryName}\" not found or ambiguous.");
            }

            $countryId = $country->id;
        }

        if ($this->present($stateName)) {
            $query = State::query();

            if ($countryId !== null) {
                $query->where('country_id', $countryId);
            }

            $state = $this->findByName($query, $this->localizer->region($stateName));

            if ($state === null) {
                return GeoResolutionResult::failed("Region \"{$stateName}\" not found or ambiguous.");
            }

            $stateId = $state->id;
        }

        if ($this->present($provinceName)) {
            $province = $this->resolveProvince($provinceName, $countryId, $stateId);

            if ($province === null) {
                return GeoResolutionResult::failed("Province \"{$provinceName}\" not found or ambiguous.");
            }

            $provinceId = $province->id;
            // Backfill a blank region/country from the resolved province (a
            // plate code identifies its province, hence region and country).
            $stateId ??= $province->state_id;
            $countryId ??= $province->country_id;
        }

        if ($this->present($cityName)) {
            $city = $this->resolveCity($cityName, $countryId, $stateId, $provinceId);

            if ($city === null) {
                return GeoResolutionResult::failed("City \"{$cityName}\" not found or ambiguous.");
            }

            $cityId = $city->id;
            $provinceId ??= $city->province_id;
            $stateId ??= $city->state_id;
            $countryId ??= $city->country_id;
        }

        return GeoResolutionResult::ok($countryId, $stateId, $provinceId, $cityId);
    }

    private function resolveProvince(string $name, ?int $countryId, ?int $stateId): ?Province
    {
        $query = Province::query();

        if ($stateId !== null) {
            $query->where('state_id', $stateId);
        } elseif ($countryId !== null) {
            $query->where('country_id', $countryId);
        }

        // A plate code (`NA`) maps to the province name; anything else (a full
        // province name already) falls through to a plain name match.
        return $this->findByName($query, $this->localizer->province($name) ?? $name);
    }

    private function resolveCity(string $name, ?int $countryId, ?int $stateId, ?int $provinceId): ?City
    {
        $query = City::query();

        if ($provinceId !== null) {
            $query->where('province_id', $provinceId);
        } elseif ($stateId !== null) {
            $query->where('state_id', $stateId);
        } elseif ($countryId !== null) {
            $query->where('country_id', $countryId);
        }

        // Strip site-label noise and translate anglicized comuni before
        // matching (a pure placeholder cleans to null -> falls back to raw,
        // which simply fails to resolve like any unknown name).
        return $this->findByName($query, $this->localizer->city($name) ?? $name);
    }

    private function present(?string $name): bool
    {
        return $name !== null && trim($name) !== '';
    }

    /**
     * Case-insensitive exact-name lookup, scoped by the query's parent filter
     * already applied. Returns null both when NOT FOUND and when AMBIGUOUS
     * (more than one match within the scope) — an ambiguous name is exactly as
     * unusable as a missing one for the import.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    private function findByName(Builder $query, string $name): ?Model
    {
        $target = mb_strtolower(trim($name));

        $matches = $query->get()->filter(
            static fn (Model $candidate): bool => mb_strtolower((string) $candidate->getAttribute('name')) === $target
        );

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
