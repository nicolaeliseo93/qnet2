<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\ReferentType;
use Illuminate\Http\Request;

/**
 * For-select projection of a ReferentType (GET /api/referent-types/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. Mirrors
 * BusinessFunctionForSelectResource.
 *
 * @mixin ReferentType
 */
class ReferentTypeForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
        ];
    }
}
