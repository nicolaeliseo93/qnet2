<?php

namespace App\Http\Requests\Geo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/cities (ADR 0010).
 *
 * Cities are fetched scoped to a parent: `province_id` (preferred, the finest
 * level) OR `state_id` (region — kept for countries without a province level and
 * for backward compatibility). At least one is mandatory, so a request with no
 * parent is a 422, never an unbounded list. When both are given, province_id
 * wins (see GeoController::cities). `search` is an optional case-insensitive
 * name filter; the result set is hard-capped (50) in the controller. No
 * authorization beyond auth:sanctum — read-only reference data.
 */
class ListCitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reference lookup: gated only by auth:sanctum, no per-resource ability.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'state_id' => ['required_without:province_id', 'nullable', 'integer', Rule::exists('states', 'id')],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function provinceId(): ?int
    {
        $provinceId = $this->validated('province_id');

        return $provinceId !== null ? (int) $provinceId : null;
    }

    public function stateId(): ?int
    {
        $stateId = $this->validated('state_id');

        return $stateId !== null ? (int) $stateId : null;
    }

    public function search(): ?string
    {
        $search = $this->validated('search');

        return is_string($search) && $search !== '' ? $search : null;
    }
}
