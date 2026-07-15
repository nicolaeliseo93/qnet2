<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\PipelineStatus;
use Illuminate\Http\Request;

/**
 * For-select projection of a PipelineStatus (GET /api/pipeline-statuses/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. Mirrors
 * SourceForSelectResource.
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
        ];
    }
}
