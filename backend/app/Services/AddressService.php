<?php

namespace App\Services;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\Users\AddressInput;
use App\Models\Address;
use App\Models\PersonalData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for addresses.
 *
 * Centralizes create/update/delete of the polymorphic Address so controllers
 * and models stay thin. Each operation runs in a transaction.
 *
 * Owns the invariant "at most one primary address per owner" (ADR 0010). Unlike
 * a contact's single-primary rule there is no `type` dimension: the key is the
 * owner alone (addressable_type + addressable_id). Two rules apply:
 *
 *  - first-address auto-primary: the very first address of an owner is forced
 *    primary, so an owner is never left without a principal address;
 *  - demote-siblings-on-set: setting an address primary demotes every other
 *    address of the same owner, inside the transaction so the relation is never
 *    left with two primaries.
 *
 * The morph relation has no DB-level constraint, so this invariant cannot live
 * in the schema and belongs here.
 */
class AddressService
{
    /**
     * Create an address for an owning model, enforcing the single-primary
     * invariant (first address auto-primary; an explicit primary demotes the
     * owner's siblings).
     */
    public function createFor(Model $owner, CreateAddress $data): Address
    {
        return DB::transaction(function () use ($owner, $data): Address {
            $attributes = $data->toAttributes();

            // The first address of an owner becomes its primary automatically,
            // so the owner always has a principal address.
            if (! $this->ownerHasAddress($owner)) {
                $attributes['is_primary'] = true;
            }

            /** @var Address $address */
            $address = $owner->addresses()->create($attributes);

            if ($address->is_primary) {
                $this->demoteSiblings($address);
            }

            return $address;
        });
    }

    /**
     * Update an existing address (full replacement of its attributes),
     * enforcing the single-primary invariant when it becomes (or stays) primary.
     */
    public function update(Address $address, CreateAddress $data): Address
    {
        return DB::transaction(function () use ($address, $data): Address {
            $address->update($data->toAttributes());

            if ($address->is_primary) {
                $this->demoteSiblings($address);
            }

            return $address;
        });
    }

    /**
     * Delete an address.
     */
    public function delete(Address $address): void
    {
        DB::transaction(fn () => $address->delete());
    }

    /**
     * Authoritatively reconcile the card's addresses against the submitted set
     * (nested user-profile write, ADR 0012).
     *
     * Diff against the card's CURRENT owned addresses: an input whose id exists
     * AND belongs to this card is updated; any other input (no id, or an id that
     * does not belong to this card) is created; finally every owned address whose
     * id is not in the kept set is deleted. Reuses createFor/update/delete so the
     * single-primary invariant is enforced once. Runs in its own transaction; the
     * nested per-operation transactions become savepoints.
     *
     * SECURITY: an id that does not belong to this card is treated as a create,
     * never an update — so a spoofed id can never mutate another card's address.
     *
     * @param  array<int, AddressInput>  $inputs
     */
    public function sync(PersonalData $card, array $inputs): void
    {
        DB::transaction(function () use ($card, $inputs): void {
            /** @var array<int, Address> $owned */
            $owned = $card->addresses()->get()->keyBy('id')->all();

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

            foreach ($owned as $id => $address) {
                if (! in_array($id, $keptIds, true)) {
                    $this->delete($address);
                }
            }
        });
    }

    /**
     * Whether the owner already has at least one address.
     */
    private function ownerHasAddress(Model $owner): bool
    {
        return $owner->addresses()->exists();
    }

    /**
     * Demote every other address of the same owner, leaving the given address as
     * the only primary.
     */
    private function demoteSiblings(Address $address): void
    {
        Address::query()
            ->where('addressable_type', $address->addressable_type)
            ->where('addressable_id', $address->addressable_id)
            ->where('is_primary', true)
            ->whereKeyNot($address->getKey())
            ->update(['is_primary' => false]);
    }
}
