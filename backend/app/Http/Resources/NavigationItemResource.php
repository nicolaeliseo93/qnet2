<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NavigationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource['key'],
            'label' => $this->resource['label'],
            'icon' => $this->resource['icon'] ?? null,
            'route' => $this->resource['route'] ?? null,
            'type' => $this->resource['type'] ?? 'item',
            'children' => self::collection($this->resource['children'] ?? []),
        ];
    }
}
