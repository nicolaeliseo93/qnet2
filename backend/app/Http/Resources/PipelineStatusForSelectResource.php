<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\PipelineStatus;
use Illuminate\Http\Request;

/**
 * For-select projection of a PipelineStatus (GET /api/pipeline-statuses/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. Mirrors
 * SourceForSelectResource. `meta.system_key` (spec 0039, D-2) lets the
 * frontend recognize/pin the two system rows in an entity-backed select
 * without a second lookup.
 *
 * @mixin PipelineStatus
 */
class PipelineStatusForSelectResource extends ForSelectResource
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
