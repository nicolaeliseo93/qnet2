<?php

namespace App\Models\Concerns;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Drop-in polymorphic contacts for any model.
 *
 * Add `use HasContacts` to an owning model and the morph is wired
 * automatically — no schema change (the `contacts` table already carries a
 * nullable `contactable` morph):
 *
 *     class PersonalData extends BaseModel
 *     {
 *         use HasContacts;
 *     }
 *
 *     $card->contacts;                          // all channels owned
 *     $card->primaryContact(ContactTypeEnum::Email); // preferred email, if any
 *     $card->delete();                          // cascades: contact rows removed
 *
 * Cleanup mirrors HasAttachments: since the morph has no DB-level foreign key,
 * the trait removes the owner's contacts on delete so no orphan rows remain.
 * The "at most one primary per owner+type" invariant lives in ContactService.
 */
trait HasContacts
{
    /**
     * When the owner is deleted, remove its contacts too, preventing orphan
     * rows. Skipped on a soft-delete (the owner still exists); runs on a real
     * delete or force-delete.
     */
    public static function bootHasContacts(): void
    {
        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->contacts()->each(static fn (Contact $contact) => $contact->delete());
        });
    }

    /**
     * All contacts owned by this model.
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
    }

    /**
     * The preferred (primary) contact of a given type for this owner, if any.
     */
    public function primaryContact(ContactTypeEnum $type): ?Contact
    {
        return $this->contacts()
            ->where('type', $type->value)
            ->where('is_primary', true)
            ->first();
    }
}
