<?php

namespace App\Notes;

use App\Models\Note;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Normalizes `parent_id` to the single-level thread invariant (spec 0052,
 * D-7): replying to a reply re-parents to that reply's OWN root, never 422.
 * Replying to a note on a DIFFERENT host record is rejected — a thread never
 * spans two entities.
 */
final class NoteThreadResolver
{
    /**
     * @throws ValidationException
     */
    public function resolveParentId(?int $parentId, Model $record, string $notableAlias): ?int
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Note::query()->find($parentId);

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => ['The selected parent note does not exist.'],
            ]);
        }

        if ($parent->notable_type !== $notableAlias || $parent->notable_id !== $record->getKey()) {
            throw ValidationException::withMessages([
                'parent_id' => ['The parent note belongs to a different record.'],
            ]);
        }

        return $parent->parent_id ?? $parent->id;
    }
}
