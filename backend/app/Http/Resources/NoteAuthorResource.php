<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `author`/mention-list projection of a User inside a Note (spec 0052
 * SHAPE Note): {id, name, avatar_url}. Deliberately minimal, distinct from
 * UserForSelectResource's {id, label, subtitle, avatar_url} — a note author
 * is not a select option.
 *
 * @mixin User
 */
class NoteAuthorResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, avatar_url: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatarDataUri(),
        ];
    }
}
