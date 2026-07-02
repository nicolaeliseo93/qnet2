<?php

namespace App\Services;

use App\DataObjects\Users\ProfileData;
use App\Models\User;

/**
 * Single source of truth for persisting a user's nested personal-data profile:
 * the card upsert plus the authoritative sync of its contacts and addresses,
 * and the derivation of the account `name` from the card (ADR 0013).
 *
 * Extracted from UserService so both the Users module (privileged CRUD) and the
 * self-service `/auth/me` flow write the profile through exactly the same path —
 * no fork, no duplicated validation/CRUD. The caller is responsible for the
 * surrounding transaction (UserService::create/update and AuthService::updateProfile
 * already open one), so this writer only performs the child operations and never
 * opens its own outer transaction; the per-entity services run their own
 * transactions, which become savepoints under the caller's.
 */
class ProfileWriter
{
    public function __construct(
        private readonly PersonalDataService $personalData,
        private readonly ContactService $contacts,
        private readonly AddressService $addresses,
    ) {}

    /**
     * Persist the nested personal-data profile for the user inside the caller's
     * transaction: upsert the owned card, then authoritatively sync its contacts
     * and addresses when those collections were submitted (a null collection
     * leaves it untouched — ADR 0012/0013). No-op when no profile was submitted.
     *
     * The owner is ALWAYS the given $user: the card morph is bound to it, so any
     * client-supplied personable_type/personable_id is irrelevant by construction.
     */
    public function write(User $user, ?ProfileData $profile): void
    {
        if ($profile === null) {
            return;
        }

        // Derive the account name from the card here, in the shared path, so both
        // the Users module and the self-service /auth/me flow keep users.name in
        // sync with the card identity (ADR 0013). forceFill avoids depending on
        // `name` being mass-assignable from request input on the self-service side.
        $user->forceFill(['name' => $profile->card->displayName()])->save();

        $card = $this->personalData->upsertFor($user, $profile->card);

        if ($profile->contacts !== null) {
            $this->contacts->sync($card, $profile->contacts);
        }

        if ($profile->addresses !== null) {
            $this->addresses->sync($card, $profile->addresses);
        }
    }
}
