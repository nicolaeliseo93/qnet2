<?php

namespace App\Services;

use App\DataObjects\Users\ProfileData;
use App\Models\Registry;

/**
 * Single source of truth for persisting a registry's nested personal-data
 * profile: the card upsert plus the authoritative sync of its contacts and
 * addresses, and the derivation of the denormalized `registries.name` from
 * the card (mirrors `referents.name`/`users.name`).
 *
 * Thin, registry-specific sibling of ReferentProfileWriter/ProfileWriter:
 * reuses the exact same owner-agnostic
 * PersonalDataService/ContactService/AddressService, so those stay
 * completely untouched (spec 0020, zero blast radius). The caller is
 * responsible for the surrounding transaction (RegistryService::create/update
 * already opens one), so this writer only performs the child operations and
 * never opens its own outer transaction.
 */
class RegistryProfileWriter
{
    public function __construct(
        private readonly PersonalDataService $personalData,
        private readonly ContactService $contacts,
        private readonly AddressService $addresses,
    ) {}

    /**
     * Persist the nested personal-data profile for the registry inside the
     * caller's transaction: upsert the owned card, then authoritatively sync
     * its contacts and addresses when those collections were submitted (a
     * null collection leaves it untouched). No-op when no profile was
     * submitted.
     *
     * The owner is ALWAYS the given $registry: the card morph is bound to
     * it, so any client-supplied personable_type/personable_id is irrelevant
     * by construction.
     */
    public function write(Registry $registry, ?ProfileData $profile): void
    {
        if ($profile === null) {
            return;
        }

        // Derive the denormalized name from the card here, the same way
        // ReferentProfileWriter/ProfileWriter do.
        $registry->forceFill(['name' => $profile->card->displayName()])->save();

        $card = $this->personalData->upsertFor($registry, $profile->card);

        if ($profile->contacts !== null) {
            $this->contacts->sync($card, $profile->contacts);
        }

        if ($profile->addresses !== null) {
            $this->addresses->sync($card, $profile->addresses);
        }
    }
}
