<?php

namespace App\Http\Requests\PersonalData;

use Illuminate\Contracts\Validation\Validator;

/**
 * Validates the payload for PUT/PATCH /api/contacts/{contact}.
 *
 * Reuses StoreContactRequest's per-type domain rules verbatim but drops the
 * owner: a contact is never re-parented through an update. Update is a full
 * replacement of the contact's attributes. Authorization stays in the controller
 * via the ContactPolicy.
 */
class UpdateContactRequest extends StoreContactRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->domainRules();
    }

    /**
     * No owner validation on update (the owner is immutable).
     */
    public function withValidator(Validator $validator): void
    {
        // Intentionally empty: the contact keeps its existing owner.
    }
}
