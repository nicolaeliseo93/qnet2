<?php

namespace App\Services;

use App\DataObjects\Users\ProfileData;
use App\Models\CompanySite;

/**
 * Single source of truth for persisting a company site's nested personal-data
 * profile: the card upsert plus the authoritative sync of its contacts and
 * addresses.
 *
 * Thin, company-site-specific sibling of RegistryProfileWriter/ProfileWriter:
 * reuses the exact same owner-agnostic
 * PersonalDataService/ContactService/AddressService, so those stay completely
 * untouched (spec 0020, zero blast radius). The caller is responsible for the
 * surrounding transaction (CompanySiteService::create/update already opens
 * one), so this writer only performs the child operations and never opens its
 * own outer transaction.
 *
 * Unlike RegistryProfileWriter it does NOT derive a denormalized `name` from
 * the card: `company_sites.name` is the site's own required column, written
 * from the scalar payload by CompanySiteService.
 */
class CompanySiteProfileWriter
{
    public function __construct(
        private readonly PersonalDataService $personalData,
        private readonly ContactService $contacts,
        private readonly AddressService $addresses,
    ) {}

    /**
     * Persist the nested personal-data profile for the site inside the
     * caller's transaction: upsert the owned card, then authoritatively sync
     * its contacts and addresses when those collections were submitted (a null
     * collection leaves it untouched). No-op when no profile was submitted.
     *
     * The owner is ALWAYS the given $companySite: the card morph is bound to
     * it, so any client-supplied personable_type/personable_id is irrelevant
     * by construction.
     */
    public function write(CompanySite $companySite, ?ProfileData $profile): void
    {
        if ($profile === null) {
            return;
        }

        $card = $this->personalData->upsertFor($companySite, $profile->card);

        if ($profile->contacts !== null) {
            $this->contacts->sync($card, $profile->contacts);
        }

        if ($profile->addresses !== null) {
            $this->addresses->sync($card, $profile->addresses);
        }
    }
}
