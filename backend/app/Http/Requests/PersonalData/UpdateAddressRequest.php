<?php

namespace App\Http\Requests\PersonalData;

use Illuminate\Contracts\Validation\Validator;

/**
 * Validates the payload for PUT/PATCH /api/addresses/{address}.
 *
 * Reuses StoreAddressRequest's domain rules verbatim but drops the owner: an
 * address is never re-parented through an update. Update is a full replacement
 * of the address's attributes. Authorization stays in the controller via the
 * AddressPolicy.
 */
class UpdateAddressRequest extends StoreAddressRequest
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
        // Intentionally empty: the address keeps its existing owner.
    }
}
