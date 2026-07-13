<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Campaign;
use Illuminate\Http\Request;

/**
 * For-select projection of a Campaign (GET /api/campaigns/for-select, spec
 * 0024): label = name, subtitle = code. Feeds the Lead form's campaign field
 * (the only consumer today — Campaign itself has no for-select of its own
 * before this spec, per the 0023 note).
 *
 * @mixin Campaign
 */
class CampaignForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->code,
        ];
    }
}
