<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Address;
use App\Models\OperationalSite;
use Illuminate\Http\Request;

/**
 * For-select projection of an OperationalSite (GET /api/operational-sites/for-select).
 *
 * A site has no own name (identity = its address, see OperationalSite): label
 * is composed from the primary address' `line1`, plus " - {city}" when the
 * address has a city; subtitle = postal_code when present (omitted otherwise,
 * ForSelectResource's null-optional rule). Relies on the Service eager-loading
 * the primary address (+ city) — never a full OperationalSiteResource.
 *
 * @mixin OperationalSite
 */
class OperationalSiteForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        /** @var Address|null $address */
        $address = $this->addresses->first();

        return [
            'id' => $this->id,
            'label' => $this->composeLabel($address),
            'subtitle' => $address?->postal_code,
        ];
    }

    private function composeLabel(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $city = $address->city?->localizedName();

        return $city === null ? (string) $address->line1 : "{$address->line1} - {$city}";
    }
}
