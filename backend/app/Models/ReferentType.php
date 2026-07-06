<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ReferentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Referent type lookup entity (spec 0016): a full-CRUD classification (name
 * only) feeding the "Referent type" select of the `referents` module.
 */
#[Fillable(['name'])]
class ReferentType extends BaseModel
{
    /** @use HasFactory<ReferentTypeFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Spec 0013 — external data migration: the source system's id for a
            // migrated type, guarded (not in #[Fillable]) so it is only ever set
            // by property assignment post-create.
            'old_id' => 'integer',
        ];
    }

    /**
     * The referents classified under this type. `referent_type_id` is
     * nullOnDelete (migration), so deleting a type never cascades a delete.
     */
    public function referents(): HasMany
    {
        return $this->hasMany(Referent::class);
    }
}
