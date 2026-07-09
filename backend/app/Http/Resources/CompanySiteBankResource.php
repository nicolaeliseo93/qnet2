<?php

namespace App\Http\Resources;

use App\Models\CompanySiteBank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanySiteBank
 */
class CompanySiteBankResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'iban' => $this->iban,
            'notes' => $this->notes,
            'is_primary' => $this->is_primary,
        ];
    }
}
