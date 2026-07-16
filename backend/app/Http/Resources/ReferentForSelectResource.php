<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Contact;
use App\Models\Referent;
use Illuminate\Http\Request;

/**
 * For-select projection of a Referent (GET /api/referents/for-select,
 * spec 0020).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. `meta`
 * (spec 0040 A-4) carries the referent's PRIMARY contacts, feeding the
 * Opportunity form's contact recap — always present (`contacts: []` when the
 * referent has no card / no primary contacts). The eager-load
 * (ReferentService::forSelect) already constrains to is_primary, so no N+1
 * and no re-filter here.
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
            'meta' => [
                'contacts' => $this->primaryContacts(),
            ],
        ];
    }

    /**
     * The referent's primary contacts as flat rows. `value` is $hidden on
     * Contact (kept out of the activity log / default serialization) but
     * deliberately re-exposed here via property access — the sanctioned,
     * authorized (referents.viewAny) projection, mirroring ContactResource.
     *
     * @return list<array<string, mixed>>
     */
    private function primaryContacts(): array
    {
        if ($this->personalData === null) {
            return [];
        }

        return $this->personalData->contacts
            ->map(static fn (Contact $contact): array => [
                'type' => $contact->type,
                'label' => $contact->label,
                'value' => $contact->value,
                'is_primary' => $contact->is_primary,
            ])
            ->all();
    }
}
