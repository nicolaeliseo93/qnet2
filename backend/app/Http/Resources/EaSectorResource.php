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
            // {id, name} per tag (spec 0019) — the Service eager-loads `tags`
            // for the returned model on create/update. `tag_ids` is the plain
            // id list of the same relation, consumed by the edit form's
            // default values while `tags` hydrates the multi-select.
            'tags' => $this->tags->map(fn ($tag): array => ['id' => $tag->id, 'name' => $tag->name])->all(),
            'tag_ids' => $this->tags->pluck('id')->values()->all(),
            'created_at' => $this->created_at,
        ];
    }
}
