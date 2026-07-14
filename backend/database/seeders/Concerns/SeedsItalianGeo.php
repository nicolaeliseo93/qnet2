<?php

namespace Database\Seeders\Concerns;

use App\Models\City;
use App\Models\Country;
use App\Models\Project;
use App\Models\Province;
use App\Models\State;
use Illuminate\Support\Collection;

/**
 * Shared geo fixtures for the demo Projects/Campaigns seeders (spec 0027).
 * Every id returned here is read back from a REAL row already seeded by
 * `php artisan locations:add` (never invented), so the produced tuples are
 * BR-4-coherent by construction (state/province/city are always resolved as
 * descendants of the SAME country/state actually picked).
 *
 * Degrades cleanly when the geo reference tables are empty (dataset not
 * loaded): `italianStates()` returns an empty collection, `geoTuple()` then
 * falls back to a country-only tuple (or all-null if not even a country
 * exists), and `refineGeo()` returns no refinement at all. A project/campaign
 * row with all four geo columns NULL is a valid DB state (D-4, the columns
 * stay nullable — `country_id` is required only at the FormRequest layer,
 * which these seeders bypass by calling the Service directly).
 */
trait SeedsItalianGeo
{
    private const int STATE_SAMPLE = 20;

    /**
     * Italy's regions ("Regione"), sampled from the `states` geo table seeded
     * by `locations:add` — this gestionale's demo data is Italian-flavoured,
     * so scoping to Italy keeps the values meaningful instead of a random
     * worldwide region. Degrades to a random sample of whatever country's
     * states exist when Italy itself is missing, and to an empty collection
     * when the geo tables have no rows at all.
     *
     * @return Collection<int, State>
     */
    private function italianStates(): Collection
    {
        $italy = Country::query()->where('iso2', 'IT')->first();

        if ($italy === null) {
            return State::query()->inRandomOrder()->limit(self::STATE_SAMPLE)->get();
        }

        return State::query()->where('country_id', $italy->id)->orderBy('name')->get();
    }

    /**
     * A deterministic, BR-4-coherent geo tuple at one of the four scope
     * depths — country / state / province / city — cycling by $index so the
     * demo shows every `geo_scope` value (AC-003). A tier degrades to the
     * nearest shallower one when the deeper reference table has no matching
     * row for the picked state/province (e.g. a region with no coded
     * province).
     *
     * @param  Collection<int, State>  $states
     * @return array{country_id: int|null, state_id: int|null, province_id: int|null, city_id: int|null}
     */
    private function geoTuple(Collection $states, int $index): array
    {
        if ($states->isEmpty()) {
            $country = Country::query()->inRandomOrder()->first();

            return ['country_id' => $country?->id, 'state_id' => null, 'province_id' => null, 'city_id' => null];
        }

        $state = $states[$index % $states->count()];

        return match ($index % 4) {
            0 => ['country_id' => $state->country_id, 'state_id' => null, 'province_id' => null, 'city_id' => null],
            1 => ['country_id' => $state->country_id, 'state_id' => $state->id, 'province_id' => null, 'city_id' => null],
            2 => $this->tupleAtProvince($state),
            default => $this->tupleAtCity($state),
        };
    }

    /**
     * @return array{country_id: int|null, state_id: int|null, province_id: int|null, city_id: int|null}
     */
    private function tupleAtProvince(State $state): array
    {
        $province = Province::query()->where('state_id', $state->id)->orderBy('name')->first();

        return [
            'country_id' => $state->country_id,
            'state_id' => $state->id,
            'province_id' => $province?->id,
            'city_id' => null,
        ];
    }

    /**
     * @return array{country_id: int|null, state_id: int|null, province_id: int|null, city_id: int|null}
     */
    private function tupleAtCity(State $state): array
    {
        $city = City::query()->where('state_id', $state->id)->orderBy('name')->first();

        return [
            'country_id' => $state->country_id,
            'state_id' => $state->id,
            'province_id' => $city?->province_id,
            'city_id' => $city?->id,
        ];
    }

    /**
     * BR-5 refinement: a geo tuple for a campaign LINKED to $project, filling
     * ONLY the levels the project leaves empty — never one it already fills
     * (that stays absent here; `CampaignService::applyGeoInheritance()` would
     * null it out anyway, this is just not sending it in the first place).
     * Deterministically alternates, by $index, between "refine one level
     * deeper" and "no refinement at all" (a linked campaign legitimately just
     * inheriting the project's scope is an equally valid BR-5 outcome), so
     * BOTH are visible in the demo data. Empty array when the project already
     * fills every level (city_id set) or has no country at all (degraded geo
     * dataset).
     *
     * @return array<string, int>
     */
    private function refineGeo(Project $project, int $index): array
    {
        if ($project->country_id === null || $project->city_id !== null) {
            return [];
        }

        if ($index % 3 === 0) {
            return [];
        }

        if ($project->state_id === null) {
            return $this->refineFromCountry($project, $index);
        }

        if ($project->province_id === null) {
            return $this->refineFromState($project, $index);
        }

        return $this->refineFromProvince($project, $index);
    }

    /**
     * @return array<string, int>
     */
    private function refineFromCountry(Project $project, int $index): array
    {
        $states = State::query()->where('country_id', $project->country_id)->orderBy('name')->get();

        if ($states->isEmpty()) {
            return [];
        }

        $state = $states[$index % $states->count()];

        if ($index % 2 === 0) {
            return ['state_id' => $state->id];
        }

        $city = City::query()->where('state_id', $state->id)->orderBy('name')->first();

        if ($city === null) {
            return ['state_id' => $state->id];
        }

        return $city->province_id !== null
            ? ['state_id' => $state->id, 'province_id' => $city->province_id, 'city_id' => $city->id]
            : ['state_id' => $state->id, 'city_id' => $city->id];
    }

    /**
     * @return array<string, int>
     */
    private function refineFromState(Project $project, int $index): array
    {
        $provinces = Province::query()->where('state_id', $project->state_id)->orderBy('name')->get();

        if ($provinces->isEmpty()) {
            $city = City::query()->where('state_id', $project->state_id)->orderBy('name')->first();

            return $city !== null ? ['city_id' => $city->id] : [];
        }

        $province = $provinces[$index % $provinces->count()];

        if ($index % 2 === 0) {
            return ['province_id' => $province->id];
        }

        $city = City::query()->where('province_id', $province->id)->orderBy('name')->first();

        return $city !== null
            ? ['province_id' => $province->id, 'city_id' => $city->id]
            : ['province_id' => $province->id];
    }

    /**
     * @return array<string, int>
     */
    private function refineFromProvince(Project $project, int $index): array
    {
        $cities = City::query()->where('province_id', $project->province_id)->orderBy('name')->get();

        return $cities->isEmpty() ? [] : ['city_id' => $cities[$index % $cities->count()]->id];
    }
}
