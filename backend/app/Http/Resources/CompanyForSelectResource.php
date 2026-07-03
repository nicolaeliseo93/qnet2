<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Company;
use Illuminate\Http\Request;

/**
 * For-select projection of a Company (GET /api/companies/for-select).
 *
 * Minimal by design (ADR 0011): label = denomination, subtitle = vat_number
 * when present (omitted otherwise, ForSelectResource's null-optional rule).
 *
 * @mixin Company
 */
class CompanyForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->denomination,
            'subtitle' => $this->vat_number,
        ];
    }
}
