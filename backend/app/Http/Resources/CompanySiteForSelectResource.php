<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\CompanySite;
use Illuminate\Http\Request;

/**
 * For-select projection of a CompanySite (GET /api/company-sites/for-select,
 * spec 0040).
 *
 * Minimal by design (ADR 0011): label = name, subtitle = the owning
 * company's denomination (null/omitted when the site has no company).
 *
 * @mixin CompanySite
 */
class CompanySiteForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->company?->denomination,
        ];
    }
}
