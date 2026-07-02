<?php

namespace App\Services;

use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\Users\ContactInput;
use App\Models\Contact;
use App\Models\PersonalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for contact channels.
 *
 * Owns the invariant "at most one primary contact per owner + type": whenever a
 * contact is created or updated as primary, every sibling of the same type on
 * the same owner is demoted, inside a transaction so the relation is never left
 * with two primaries. The morph relation has no DB-level constraint, so this
 * invariant cannot live in the schema and belongs here.
 */
class ContactService
{
    /**
     * Create a contact for an owning model, enforcing the single-primary
     * invariant when the new contact is primary.
     */
    public function createFor(Model $owner, CreateContact $data): Contact
    {
        return DB::transaction(function () use ($owner, $data): Contact {
            /** @var Contact $contact */
            $contact = $owner->contacts()->create($data->toAttributes());

            if ($contact->is_primary) {
                $this->demoteSiblings($contact);
            }

            return $contact;
        });
    }

    /**
     * Update a contact, enforcing the single-primary invariant when it becomes
     * (or stays) primary.
     */
    public function update(Contact $contact, CreateContact $data): Contact
    {
        return DB::transaction(function () use ($contact, $data): Contact {
            $contact->update($data->toAttributes());

            if ($contact->is_primary) {
                $this->demoteSiblings($contact);
            }

            return $contact;
        });
    }

    /**
     * Promote a contact to primary, demoting any sibling of the same type.
     */
    public function makePrimary(Contact $contact): Contact
    {
        return DB::transaction(function () use ($contact): Contact {
            $contact->update(['is_primary' => true]);
            $this->demoteSiblings($contact);

            return $contact;
        });
    }

    /**
     * Delete a contact.
     */
    public function delete(Contact $contact): void
    {
        DB::transaction(fn () => $contact->delete());
    }

    /**
     * Authoritatively reconcile the card's contacts against the submitted set
     * (nested user-profile write, ADR 0012).
     *
     * Diff against the card's CURRENT owned contacts: an input whose id exists
     * AND belongs to this card is updated; any other input (no id, or an id that
     * does not belong to this card) is created; finally every owned contact whose
     * id is not in the kept set is deleted. Reuses createFor/update/delete so the
     * single-primary invariant is enforced once. Runs in its own transaction; the
     * nested per-operation transactions become savepoints.
     *
     * SECURITY: an id that does not belong to this card is treated as a create,
     * never an update — so a spoofed id can never mutate another card's contact.
     *
     * @param  array<int, ContactInput>  $inputs
     */
    public function sync(PersonalData $card, array $inputs): void
    {
        DB::transaction(function () use ($card, $inputs): void {
            /** @var array<int, Contact> $owned */
            $owned = $card->contacts()->get()->keyBy('id')->all();

            $keptIds = [];

            foreach ($inputs as $input) {
                $existing = $input->id !== null ? ($owned[$input->id] ?? null) : null;

                if ($existing !== null) {
                    $this->update($existing, $input->data);
                    $keptIds[] = $existing->id;

                    continue;
                }

                $created = $this->createFor($card, $input->data);
                $keptIds[] = $created->id;
            }

            foreach ($owned as $id => $contact) {
                if (! in_array($id, $keptIds, true)) {
                    $this->delete($contact);
                }
            }
        });
    }

    /**
     * Demote every other contact of the same type on the same owner, leaving
     * the given contact as the only primary of its type.
     */
    private function demoteSiblings(Contact $contact): void
    {
        Contact::query()
            ->where('contactable_type', $contact->contactable_type)
            ->where('contactable_id', $contact->contactable_id)
            ->where('type', $contact->type->value)
            ->where('is_primary', true)
            ->whereKeyNot($contact->getKey())
            ->update(['is_primary' => false]);
    }
}
