<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Tag lookup entity (spec 0019): a full-CRUD classification (name only),
 * mirroring Source (spec 0018). A standalone lookup: the polymorphic tagging
 * of other entities was retired (the `taggables` pivot is dropped), so a Tag
 * currently has no producers of associations.
 */
#[Fillable(['name'])]
class Tag extends BaseModel
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Spec 0013 — external data migration: the source system's id for a
            // migrated tag, guarded (not in #[Fillable]) so it is only ever set
            // by property assignment post-create.
            'old_id' => 'integer',
        ];
    }
}
