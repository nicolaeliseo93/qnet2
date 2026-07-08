<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\EaSectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EA sector tree node (spec 0018): unlimited-depth parent/child hierarchy,
 * a standalone lookup used to classify Anagrafiche in the future (no such
 * relation exists yet — see spec 0018 scope).
 */
#[Fillable(['name', 'parent_id'])]
class EaSector extends BaseModel
{
    /** @use HasFactory<EaSectorFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Spec 0013 — external data migration: the source system's id for a
            // migrated sector, guarded (not in #[Fillable]) so it is only ever
            // set by property assignment post-create. Also the remap key for the
            // self-referential `parent_id` (child → parent via old_id).
            'old_id' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
