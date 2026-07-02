<?php

namespace App\Http\Requests\Geo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/provinces (ADR 0010).
 *
 * state_id is mandatory: provinces are always fetched scoped to a state
 * (region) in the geo cascade, so a missing or unknown parent is a 422, never
 * an unbounded list. No authorization beyond auth:sanctum — countries/states/
 * provinces/cities are read-only reference data with no per-resource permission.
 */
class ListProvincesRequest extends FormRequest
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
            'state_id' => ['required', 'integer', Rule::exists('states', 'id')],
        ];
    }

    public function stateId(): int
    {
        return (int) $this->validated('state_id');
    }
}
