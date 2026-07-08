<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\EaSector;
use Illuminate\Http\Request;

/**
 * For-select projection of an EaSector (GET /api/ea-sectors/for-select,
 * spec 0020).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. Mirrors
 * SourceForSelectResource.
 *
 * @mixin EaSector
 */
class EaSectorForSelectResource extends ForSelectResource
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
