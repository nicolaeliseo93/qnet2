<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Lead;
use Illuminate\Http\Request;

/**
 * For-select projection of a Lead (GET /api/leads/for-select, amendment
 * rev.1 A-1): label = the lead's registry name (a Lead has no own name
 * column, mirrors LeadResource/OpportunityResource's `lead.label`; spec 0041
 * D-1: the contact is now an Anagrafica, not a Referent), subtitle = the
 * campaign's code, falling back to its name. Feeds the Opportunity form's
 * "Lead" select (spec 0040).
 *
 * @mixin Lead
 */
class LeadForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->registry?->name ?? '',
            'subtitle' => $this->campaign?->code ?? $this->campaign?->name,
        ];
    }
}
