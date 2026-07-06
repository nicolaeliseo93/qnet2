<?php

namespace App\Services;

use App\DataObjects\Referents\CreateReferentData;
use App\DataObjects\Referents\UpdateReferentData;
use App\DataObjects\Users\ProfileData;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business logic for the `referents` resource (spec 0016): create/update/
 * delete, mirroring UserService's nested personal-data write discipline but
 * without roles/employment — a referent is only the anagraphic + the
 * classification/scope/notes.
 */
class ReferentService
{
    /**
     * Relations eager-loaded on the referent returned by create()/update()/
     * loadProfileTree() so the ReferentResource can emit the nested
     * referent_type + personal_data tree without a second request/N+1.
     *
     * @var array<int, string>
     */
    private const array WRITE_RESULT_RELATIONS = [
        'referentType',
        'personalData.contacts',
        'personalData.addresses',
    ];

    public function __construct(private readonly ReferentProfileWriter $profileWriter) {}

    /**
     * Eager-load the nested read tree so a plain GET /referents/{referent}
     * returns the SAME shape create()/update() do. Single source of truth
     * for the relation set (WRITE_RESULT_RELATIONS).
     */
    public function loadProfileTree(Referent $referent): Referent
    {
        return $referent->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Create a new referent. `personal_data` is REQUIRED (it is the only
     * source of the derived `referents.name` — mirrors UserService::create).
     */
    public function create(User $actor, CreateReferentData $data, ?ProfileData $profile): Referent
    {
        if ($profile === null) {
            // StoreReferentRequest guarantees a profile (it is the only source
            // of the derived name); a null here is a programming error/bypass.
            throw new InvalidArgumentException('A personal-data profile is required to create a referent (its name is derived from the card).');
        }

        $referent = DB::transaction(function () use ($data, $profile): Referent {
            $attributes = $data->attributes();
            // `referents.name` is NOT NULL but the authoritative value is
            // derived by ReferentProfileWriter from the card (single
            // derivation point). Seed only a non-null placeholder here to
            // satisfy the constraint at INSERT.
            $attributes['name'] = '';

            $referent = Referent::create($attributes);

            $this->profileWriter->write($referent, $profile);

            return $referent;
        });

        return $referent->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Update an existing referent. Only keys present in $data are touched, so
     * partial (PATCH) updates leave untouched fields as-is. When $profile is
     * provided, the card is upserted/synced in the same transaction; a null
     * $profile leaves the card untouched.
     */
    public function update(User $actor, Referent $referent, UpdateReferentData $data, ?ProfileData $profile): Referent
    {
        $referent = DB::transaction(function () use ($referent, $data, $profile): Referent {
            $attributes = $data->submittedAttributes();

            if ($attributes !== []) {
                $referent->update($attributes);
            }

            $this->profileWriter->write($referent, $profile);

            return $referent;
        });

        return $referent->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Delete the referent. Its personal-data card (and the card's own
     * contacts/addresses) cascade away via HasPersonalData.
     */
    public function delete(Referent $referent): void
    {
        $referent->delete();
    }
}
