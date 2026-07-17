<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\State;
use Illuminate\Http\Request;

/**
 * For-select projection of a State (GET /api/states/for-select).
 *
 * label = name, subtitle = the parent country's name (omitted when the
 * relation has no match, ForSelectResource's null-optional rule). Relies on
 * StateForSelectController eager-loading `country:id,name` — never a second
 * query per row.
 *
 * @mixin State
 */
class StateForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->localizedName(),
            'subtitle' => $this->country?->localizedName(),
        ];
    }
}
