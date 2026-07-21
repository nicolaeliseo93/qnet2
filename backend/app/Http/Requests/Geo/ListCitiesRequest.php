<?php

namespace App\Http\Requests\Geo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/cities (ADR 0010).
 *
 * Cities are fetched scoped to a parent: `province_id` (preferred, the finest
 * level) OR `state_id` (region — kept for countries without a province level and
 * for backward compatibility) OR, for city-first selection, a `search` term
 * alone (an unscoped name lookup, still hard-capped). At least one of the three
 * is mandatory, so a request with no parent AND no search is a 422, never an
 * unbounded list. When both parents are given, province_id wins (see
 * GeoController::cities). `search` is a case-insensitive name filter; the result
 * set is hard-capped (50) in the controller. No authorization beyond
 * auth:sanctum — read-only reference data.
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
            // A parentless request is allowed only with a non-empty `search`
            // (city-first lookup); still hard-capped in the controller.
            'state_id' => ['required_without_all:province_id,search', 'nullable', 'integer', Rule::exists('states', 'id')],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'offset' => ['sometimes', 'integer', 'min:0'],
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

    /**
     * Zero-based row offset for keyset-free paging; defaults to the first page.
     */
    public function offset(): int
    {
        return (int) ($this->validated('offset') ?? 0);
    }
}
