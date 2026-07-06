<?php

namespace App\Http\Resources;

use App\Models\Referent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Referent
 */
class ReferentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'referent_type_id' => $this->referent_type_id,
            // {id, name} idratazione select (ADR 0011) or null — always
            // present as a key (spec 0016 data_contract), never omitted. The
            // Service always eager-loads `referentType` for the returned
            // model, so this never triggers a lazy load.
            'referent_type' => $this->referentType !== null
                ? ['id' => $this->referentType->id, 'name' => $this->referentType->name]
                : null,
            'contact_scope' => $this->contact_scope,
            'notes' => $this->notes,
            // The nested personal-data tree, or null — always present as a
            // key, mirroring `referent_type` above (the Service always
            // eager-loads `personalData.contacts`/`personalData.addresses`).
            'personal_data' => $this->personalData !== null
                ? new PersonalDataResource($this->personalData)
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
