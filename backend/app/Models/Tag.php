<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Tag lookup entity (spec 0019): a full-CRUD classification (name only),
 * mirroring Source (spec 0018). Unlike Source, a Tag is REUSABLE: it attaches
 * to any entity through the polymorphic `taggables` pivot. `eaSectors()` is
 * the first (and currently only) producer of associations, exposed here for
 * cleanliness/tests — the delete guard queries the pivot table directly.
 */
#[Fillable(['name'])]
class Tag extends BaseModel
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, LogsModelActivity;

    public function eaSectors(): MorphToMany
    {
        return $this->morphedByMany(EaSector::class, 'taggable');
    }
}
