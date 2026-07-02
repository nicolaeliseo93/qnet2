<?php

namespace App\Models\Concerns;

use App\Models\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Drop-in polymorphic addresses for any model.
 *
 * Add `use HasAddresses` to an owning model and the morph is wired
 * automatically — no schema change (the `addresses` table already carries a
 * nullable `addressable` morph):
 *
 *     class PersonalData extends BaseModel
 *     {
 *         use HasAddresses;
 *     }
 *
 *     $card->addresses;        // all addresses owned
 *     $card->delete();          // cascades: address rows are removed too
 *
 * Cleanup mirrors HasAttachments: since the morph has no DB-level foreign key,
 * the trait removes the owner's addresses on delete so no orphan rows remain.
 */
trait HasAddresses
{
    /**
     * When the owner is deleted, remove its addresses too, preventing orphan
     * rows. Skipped on a soft-delete (the owner still exists); runs on a real
     * delete or force-delete.
     */
    public static function bootHasAddresses(): void
    {
        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->addresses()->each(static fn (Address $address) => $address->delete());
        });
    }

    /**
     * All addresses owned by this model.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }
}
