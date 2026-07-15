<?php

namespace App\Imports\Leads;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Models\Contact;
use App\Models\Referent;

/**
 * Builds the `ProfileData` (card + contacts + address) ReferentService needs
 * from a staged leads-import row's merged mapped+recognized values (spec
 * 0033: `full_name`/`first_name`/`last_name`/`company_name`/`tax_code`/
 * `vat_number` for the card, `email`/`phone`/`mobile` for contacts,
 * `street`/`postal_code` + the GeoRecognizer's `*_id` for the address).
 *
 * The `update_existing` strategy reuses the SAME shape but MERGES onto the
 * matched Referent's current contacts/addresses instead of replacing them
 * wholesale: ContactService::sync()/AddressService::sync() are authoritative
 * (an omitted row is deleted), so a row that only carries an email must never
 * wipe out the Referent's existing phone/pec/website channels or its address
 * — every currently-owned row not touched by this import is re-submitted
 * with its own id + unchanged data, only the matching type(s)/the address are
 * overwritten with the row's new value.
 */
final class LeadProfileBuilder
{
    /**
     * `create_new` shape: a fresh card, no existing rows to preserve.
     *
     * @param  array<string, mixed>  $mapped
     */
    public function build(array $mapped): ProfileData
    {
        return new ProfileData(
            card: $this->buildCard($mapped),
            contacts: $this->buildContacts($mapped),
            addresses: $this->buildAddresses($mapped),
        );
    }

    /**
     * `update_existing` shape: the card is replaced (an import row is always
     * authoritative on the anagraphic fields it carries), but contacts/
     * addresses the row does NOT mention are preserved untouched.
     *
     * @param  array<string, mixed>  $mapped
     */
    public function buildForUpdate(Referent $referent, array $mapped): ProfileData
    {
        return new ProfileData(
            card: $this->buildCard($mapped),
            contacts: $this->mergeContacts($referent, $mapped),
            addresses: $this->mergeAddresses($referent, $mapped),
        );
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function buildCard(array $mapped): CreatePersonalData
    {
        $companyName = $this->value($mapped, 'company_name');

        return new CreatePersonalData(
            type: $companyName !== null ? PersonalDataTypeEnum::Company : PersonalDataTypeEnum::Individual,
            firstName: $this->value($mapped, 'first_name'),
            lastName: $this->value($mapped, 'last_name'),
            companyName: $companyName,
            taxCode: $this->value($mapped, 'tax_code'),
            vatNumber: $this->value($mapped, 'vat_number'),
        );
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, ContactInput>|null
     */
    private function buildContacts(array $mapped): ?array
    {
        $values = $this->contactValues($mapped);

        if ($values === []) {
            return null;
        }

        $inputs = [];
        $primaryAssigned = false;

        foreach ($values as $typeValue => $value) {
            $inputs[] = new ContactInput(null, new CreateContact(
                ContactTypeEnum::from($typeValue),
                $value,
                null,
                ! $primaryAssigned,
            ));
            $primaryAssigned = true;
        }

        return $inputs;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, ContactInput>|null null leaves the Referent's
     *                                       contacts entirely untouched (the row carries none of email/phone/mobile)
     */
    private function mergeContacts(Referent $referent, array $mapped): ?array
    {
        $newValues = $this->contactValues($mapped);

        if ($newValues === []) {
            return null;
        }

        $owned = $referent->personalData?->contacts ?? collect();
        $inputs = [];
        $touchedTypes = [];

        /** @var Contact $contact */
        foreach ($owned as $contact) {
            $typeValue = $contact->type->value;

            if (! in_array($typeValue, $touchedTypes, true) && array_key_exists($typeValue, $newValues)) {
                $inputs[] = new ContactInput($contact->id, new CreateContact(
                    $contact->type,
                    $newValues[$typeValue],
                    $contact->label,
                    $contact->is_primary,
                ));
                $touchedTypes[] = $typeValue;

                continue;
            }

            // Preserve every other currently-owned contact (different type,
            // or an extra row of an already-touched type) unchanged, so the
            // authoritative sync() never deletes it.
            $inputs[] = new ContactInput($contact->id, new CreateContact(
                $contact->type,
                $contact->value,
                $contact->label,
                $contact->is_primary,
            ));
        }

        foreach ($newValues as $typeValue => $value) {
            if (in_array($typeValue, $touchedTypes, true)) {
                continue;
            }

            $inputs[] = new ContactInput(null, new CreateContact(ContactTypeEnum::from($typeValue), $value));
        }

        return $inputs;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, string> contact type value => raw value
     */
    private function contactValues(array $mapped): array
    {
        $values = [];

        foreach (LeadContactFields::map() as $field => $type) {
            $value = $this->value($mapped, $field);

            if ($value !== null) {
                $values[$type->value] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, AddressInput>|null
     */
    private function buildAddresses(array $mapped): ?array
    {
        $address = $this->buildAddressData($mapped);

        return $address === null ? null : [new AddressInput(null, $address)];
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, AddressInput>|null null leaves the Referent's
     *                                       addresses untouched (the row carries no `street`)
     */
    private function mergeAddresses(Referent $referent, array $mapped): ?array
    {
        $address = $this->buildAddressData($mapped);

        if ($address === null) {
            return null;
        }

        $existing = $referent->personalData?->addresses?->firstWhere('is_primary', true)
            ?? $referent->personalData?->addresses?->first();

        return [new AddressInput($existing?->id, $address)];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function buildAddressData(array $mapped): ?CreateAddress
    {
        $street = $this->value($mapped, 'street');

        if ($street === null) {
            return null;
        }

        return new CreateAddress(
            line1: $street,
            postalCode: $this->value($mapped, 'postal_code'),
            cityId: $this->id($mapped, 'city_id'),
            provinceId: $this->id($mapped, 'province_id'),
            stateId: $this->id($mapped, 'state_id'),
            countryId: $this->id($mapped, 'country_id'),
            isPrimary: true,
        );
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function value(array $mapped, string $field): ?string
    {
        $value = trim((string) ($mapped[$field] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function id(array $mapped, string $field): ?int
    {
        $value = $mapped[$field] ?? null;

        return $value === null ? null : (int) $value;
    }
}
