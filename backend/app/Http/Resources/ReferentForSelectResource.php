<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Referent;
use Illuminate\Http\Request;

/**
 * For-select projection of a Referent (GET /api/referents/for-select,
 * spec 0020).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. Mirrors
 * SourceForSelectResource.
 *
 * @mixin Referent
 */
class ReferentForSelectResource extends ForSelectResource
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
