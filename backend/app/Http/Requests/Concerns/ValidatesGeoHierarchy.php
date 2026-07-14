<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\City;
use App\Models\Province;
use App\Models\State;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared BR-4 geo-hierarchy consistency check (spec 0027), reused by the four
 * Project/Campaign FormRequests. A plain `exists:` rule only confirms each FK
 * points at a real row; this additionally confirms the four levels form a
 * single, consistent Country -> State -> Province -> City chain:
 *   - state.country_id == country_id
 *   - province.state_id == state_id (province requires state)
 *   - city.state_id == state_id (city requires state, NOT province)
 *   - when province_id is also set: city.province_id == province_id
 * A violation is a 422 on the offending (child) field. Query-parametrized
 * (Eloquent `where`), never raw SQL on input.
 *
 * Campaigns validate this against the MERGED tuple (BR-5): the caller
 * resolves country/state/province/city to whatever tuple is EFFECTIVE (its
 * own submitted value, or the linked project's) — this concern only checks
 * internal consistency of the tuple it is handed.
 */
trait ValidatesGeoHierarchy
{
    /**
     * @param  array{country_id: int|null, state_id: int|null, province_id: int|null, city_id: int|null}  $geo
     */
    protected function validateGeoHierarchy(Validator $validator, array $geo): void
    {
        $countryId = $geo['country_id'];
        $stateId = $geo['state_id'];
        $provinceId = $geo['province_id'];
        $cityId = $geo['city_id'];

        if ($stateId !== null && ! $this->stateBelongsToCountry($stateId, $countryId)) {
            $validator->errors()->add('state_id', 'The selected state does not belong to the selected country.');

            return;
        }

        if ($provinceId !== null && ($stateId === null || ! $this->provinceBelongsToState($provinceId, $stateId))) {
            $validator->errors()->add('province_id', 'The selected province does not belong to the selected state.');

            return;
        }

        if ($cityId === null) {
            return;
        }

        if ($stateId === null || ! $this->cityBelongsToState($cityId, $stateId)) {
            $validator->errors()->add('city_id', 'The selected city does not belong to the selected state.');

            return;
        }

        if ($provinceId !== null && ! $this->cityBelongsToProvince($cityId, $provinceId)) {
            $validator->errors()->add('city_id', 'The selected city does not belong to the selected province.');
        }
    }

    private function stateBelongsToCountry(int $stateId, ?int $countryId): bool
    {
        return State::query()->whereKey($stateId)->where('country_id', $countryId)->exists();
    }

    private function provinceBelongsToState(int $provinceId, int $stateId): bool
    {
        return Province::query()->whereKey($provinceId)->where('state_id', $stateId)->exists();
    }

    private function cityBelongsToState(int $cityId, int $stateId): bool
    {
        return City::query()->whereKey($cityId)->where('state_id', $stateId)->exists();
    }

    private function cityBelongsToProvince(int $cityId, int $provinceId): bool
    {
        return City::query()->whereKey($cityId)->where('province_id', $provinceId)->exists();
    }
}
