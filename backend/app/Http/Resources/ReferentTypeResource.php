<?php

namespace App\Http\Resources;

use App\Models\ReferentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferentType
 */
class ReferentTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at,
        ];
    }
}
