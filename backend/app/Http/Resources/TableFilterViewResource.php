<?php

namespace App\Http\Resources;

use App\Enums\FilterViewVisibility;
use App\Models\TableFilterView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TableFilterView
 */
class TableFilterViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $owned = $this->user_id === $request->user()?->id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'filters' => (object) $this->filters,
            // Advanced filters (spec 0032) captured by this saved view.
            'advanced_filters' => (object) ($this->advanced_filters ?? []),
            'visibility' => $this->visibility->value,
            'owned' => $owned,
            // Only surfaced for a shared view NOT owned by the actor ("shared by
            // X"). Display name only — never the owner's email/PII.
            'owner_name' => (! $owned && $this->visibility === FilterViewVisibility::Shared)
                ? $this->user->name
                : null,
        ];
    }
}
