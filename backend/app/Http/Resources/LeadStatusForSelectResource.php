<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\LeadStatus;
use Illuminate\Http\Request;

/**
 * For-select projection of a LeadStatus (GET /api/lead-statuses/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. Mirrors
 * PipelineStatusForSelectResource. `meta.system_key` (spec 0039, D-2) lets
 * the frontend recognize/pin the two system rows in an entity-backed select
 * without a second lookup.
 *
 * @mixin LeadStatus
 */
class LeadStatusForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'meta' => ['system_key' => $this->system_key],
        ];
    }
}
