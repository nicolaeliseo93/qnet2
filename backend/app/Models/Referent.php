<?php

namespace App\Models;

use App\Enums\ReferentContactScopeEnum;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasPersonalData;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ReferentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contact person/entity (spec 0016) that reuses the `users` anagraphic
 * stack unchanged via `HasPersonalData` (morph `personable`): the referent
 * owns a personal-data card, which in turn owns its own contacts/addresses.
 *
 * `name` is denormalized display data derived from the card (mirrors
 * `users.name`, see ReferentProfileWriter). `referent_type_id` is an
 * optional classification; `contact_scope` distinguishes internal/external
 * referents.
 */
#[Fillable(['name', 'referent_type_id', 'contact_scope', 'notes'])]
class Referent extends BaseModel
{
    /** @use HasFactory<ReferentFactory> */
    use HasFactory, HasPersonalData, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_scope' => ReferentContactScopeEnum::class,
            // Spec 0013 — external data migration: the source system's id for a
            // migrated referent, guarded (not in #[Fillable]) so it is only ever
            // set by property assignment post-create.
            'old_id' => 'integer',
        ];
    }

    /**
     * The referent's classification, if any (nullOnDelete: removing the type
     * just clears it here, it never cascades a delete).
     */
    public function referentType(): BelongsTo
    {
        return $this->belongsTo(ReferentType::class);
    }
}
