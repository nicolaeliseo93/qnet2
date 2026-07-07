<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\EaSectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * EA sector tree node (spec 0018): unlimited-depth parent/child hierarchy,
 * a standalone lookup used to classify Anagrafiche in the future (no such
 * relation exists yet — see spec 0018 scope). Also the first producer of
 * tag associations (spec 0019) via the polymorphic `taggables` pivot.
 */
#[Fillable(['name', 'parent_id'])]
class EaSector extends BaseModel
{
    /** @use HasFactory<EaSectorFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * Detach the sector's tags on delete: `taggables.taggable_id` has no db
     * foreign key (polymorphic morph), so this prevents an orphan pivot row
     * (spec 0019).
     */
    protected static function booted(): void
    {
        static::deleting(function (EaSector $sector): void {
            $sector->tags()->detach();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Tags attached to this sector via the polymorphic `taggables` pivot
     * (spec 0019).
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
