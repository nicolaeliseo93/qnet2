<?php

namespace App\Http\Requests\Geo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/states (ADR 0010).
 *
 * country_id is mandatory: states are always fetched scoped to a country in the
 * geo cascade, so a missing or unknown parent is a 422, never an unbounded list.
 * No authorization beyond auth:sanctum — countries/states/cities are read-only
 * reference data with no per-resource permission.
 */
class ListStatesRequest extends FormRequest
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
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
        ];
    }

    public function countryId(): int
    {
        return (int) $this->validated('country_id');
    }
}
