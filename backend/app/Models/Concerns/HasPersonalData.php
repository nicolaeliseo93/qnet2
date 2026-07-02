<?php

namespace App\Models\Concerns;

use App\Models\PersonalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Drop-in polymorphic personal-data card for any model.
 *
 * Add `use HasPersonalData` to an owning model and the morph is wired
 * automatically — no schema change (the `personal_data` table already carries a
 * nullable `personable` morph). Each owner has at most one card (morphOne):
 *
 *     class User extends Authenticatable
 *     {
 *         use HasPersonalData;
 *     }
 *
 *     $user->personalData;             // the card (or null)
 *     $user->delete();                  // cascades: the card (and its own
 *                                       // contacts/addresses) is removed too
 *
 * Cleanup mirrors HasAttachments: since the morph has no DB-level foreign key,
 * the trait removes the owner's card on delete so no orphan row remains. The
 * card in turn cascades its own contacts and addresses via its traits.
 */
trait HasPersonalData
{
    /**
     * When the owner is deleted, remove its personal-data card too, preventing
     * an orphan row. Skipped on a soft-delete (the owner still exists); runs on
     * a real delete or force-delete.
     */
    public static function bootHasPersonalData(): void
    {
        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->personalData()->first()?->delete();
        });
    }

    /**
     * The single personal-data card owned by this model.
     */
    public function personalData(): MorphOne
    {
        return $this->morphOne(PersonalData::class, 'personable');
    }
}
