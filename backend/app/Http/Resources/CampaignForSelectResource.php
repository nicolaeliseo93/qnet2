<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Address;
use App\Models\Campaign;
use Illuminate\Http\Request;

/**
 * For-select projection of a Campaign (GET /api/campaigns/for-select, spec
 * 0024): label = name, subtitle = code. Feeds the Lead form's campaign field
 * (the only consumer today — Campaign itself has no for-select of its own
 * before this spec, per the 0023 note). `meta.operational_site` (prefill-
 * modifiable sede) carries {id, label} — same shape ProjectForSelectResource
 * exposes — so the Lead form can prefill the Sede from the chosen campaign,
 * with no extra request. The Lead's Regione stays free/user-editable, never
 * auto-filled from the sede (user directive 2026-07-21): no state_id/
 * state_label here.
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
            'meta' => [
                'operational_site' => $this->summarizeOperationalSite($this->operationalSite),
            ],
        ];
    }

    /**
     * @return array{id: int, label: string}|null
     */
    private function summarizeOperationalSite(mixed $site): ?array
    {
        if ($site === null) {
            return null;
        }

        /** @var Address|null $address */
        $address = $site->addresses->first();

        return ['id' => $site->id, 'label' => $this->composeSiteLabel($address)];
    }

    private function composeSiteLabel(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $city = $address->city?->localizedName();

        return $city === null ? (string) $address->line1 : "{$address->line1} - {$city}";
    }
}
