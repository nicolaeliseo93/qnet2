<?php

namespace App\Http\Resources;

use App\Models\EaSector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EaSector
 */
class EaSectorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'parent' => $this->parent !== null ? ['id' => $this->parent->id, 'name' => $this->parent->name] : null,
            'created_at' => $this->created_at,
        ];
    }
}
