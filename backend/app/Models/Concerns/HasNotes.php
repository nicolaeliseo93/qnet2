<?php

namespace App\Models\Concerns;

use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Drop-in polymorphic collaborative notes for any model (spec 0052, D-9).
 *
 * Add `use HasNotes` to an owning model and the discussion thread is wired
 * automatically — no schema change (the `notes` table already carries a
 * nullable `notable` morph), no extra setup:
 *
 *     class Opportunity extends BaseModel
 *     {
 *         use HasNotes;
 *     }
 *
 *     $opportunity->notes;                          // every note (roots + replies)
 *     $opportunity->notes()->whereNull('parent_id'); // roots only
 *
 * Mirrors HasAttachments' owner-side surface. All authoring/thread/mention
 * logic stays in NoteService (agnostic, models remain thin); the trait is
 * just the relation any future entity opts into with one line.
 */
trait HasNotes
{
    /**
     * Every note owned by this model — roots AND replies alike (the flat
     * thread, D-7). Excludes soft-deleted notes automatically via `Note`'s
     * own `SoftDeletes` global scope.
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }
}
