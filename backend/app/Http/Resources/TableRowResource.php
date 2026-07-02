<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single row of any domain's table (generic SSRM data endpoint).
 *
 * Domain-agnostic: it does NOT know domain fields and does NOT recompute
 * permissions. It passes through the already-mapped associative array produced
 * by TableDefinition::mapRow() (real fields only, hidden never exposed) with the
 * per-row actions[] already attached by TableService. Keeping authorization +
 * row shaping in the definition/service is required by the contract.
 *
 * Underlying resource is an associative array, not an Eloquent model.
 *
 * @property array<string, mixed> $resource
 */
class TableRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
