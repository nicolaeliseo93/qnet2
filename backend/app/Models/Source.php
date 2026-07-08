<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Source lookup entity (spec 0018): a full-CRUD classification (name only)
 * used to classify the provenance of registry records ("Anagrafiche"). The
 * target foreign key is deferred until that entity exists (see spec 0018).
 */
#[Fillable(['name'])]
class Source extends BaseModel
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Spec 0013 — external data migration: the source system's id for a
            // migrated source, guarded (not in #[Fillable]) so it is only ever
            // set by property assignment post-create.
            'old_id' => 'integer',
        ];
    }
}
