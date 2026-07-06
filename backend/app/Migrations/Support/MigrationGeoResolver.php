<?php

namespace App\Migrations\Support;

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Support\Geo\ItalianGeoLocalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves an external system's geo strings (country/region/province/city) to
 * qnet's geo ids. Shared by every migration source whose records carry the
 * same address shape (CompaniesSource, OperationalSitesSource and any future
 * import) — the matching lives here ONCE, so a new source gets it for free.
 *
 * Two layers make the legacy strings resolvable against the ENGLISH reference
 * dataset (world.sql):
 *  1. ItalianGeoLocalizer translates the Italian value / province plate code to
 *     the reference spelling (`Italia`->`Italy`, `Sicilia`->`Sicily`, `NA`->
 *     `Naples`), and strips site-label noise off the `comune`.
 *  2. The lookup itself is case-insensitive (LIKE, wildcards escaped), so a
 *     legacy `FRATTAMAGGIORE` still matches the dataset's `Frattamaggiore`.
 *
 * Each level is looked up scoped to its resolved parent. A level whose value was
 * supplied but does not resolve (unknown value, or an unresolved parent) is a
 * non-fatal warning; levels are independent, so partial geo data still resolves
 * whatever it can.
 */
class MigrationGeoResolver
{
    public function __construct(private readonly ItalianGeoLocalizer $localizer) {}

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

        $id = $this->matchByName(Country::query(), $this->localizer->country($name));

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

        $id = $this->matchByName(
            State::query()->where('country_id', $countryId),
            $this->localizer->region($name),
        );

        if ($id === null) {
            $warnings[] = "Unresolved region '{$name}'.";
        }

        return $id;
    }

    /**
     * The legacy province value is a plate code (`NA`, `RG`): translate it to a
     * province name, then match within the resolved state. An unknown code has
     * no textual fallback, so it resolves to null with a warning.
     *
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

        $canonical = $this->localizer->province($name);

        $id = $canonical === null
            ? null
            : $this->matchByName(Province::query()->where('state_id', $stateId), $canonical);

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

        $canonical = $this->localizer->city($name);

        $query = City::query()
            ->where('state_id', $stateId)
            ->when($provinceId !== null, fn (Builder $inner) => $inner->where('province_id', $provinceId));

        $id = $canonical === null ? null : $this->matchByName($query, $canonical);

        if ($id === null) {
            $warnings[] = "Unresolved city '{$name}'.";
        }

        return $id;
    }

    /**
     * Case-insensitive exact match on `name` (LIKE with the value's own
     * wildcards escaped — no pattern search, just collation-independent
     * equality). Returns the matched row id, or null.
     *
     * @param  Builder<*>  $query
     */
    private function matchByName(Builder $query, string $name): ?int
    {
        /** @var int|null $id */
        $id = $query->where('name', 'like', addcslashes($name, '\\%_'))->value('id');

        return $id;
    }

    private function blankToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
