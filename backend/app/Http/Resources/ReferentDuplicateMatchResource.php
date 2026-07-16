<?php

namespace App\Http\Resources;

use App\DataObjects\Referents\ReferentDuplicateMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wire shape of a single ReferentDuplicateFinder match (spec 0037):
 * id/name/matched_on only — no contact value or tax_code ever leaves the
 * Service (AC-005, no PII of another referent in the response).
 *
 * @mixin ReferentDuplicateMatch
 */
class ReferentDuplicateMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'referent_id' => $this->referentId,
            'name' => $this->name,
            'matched_on' => $this->matchedOn,
        ];
    }
}
