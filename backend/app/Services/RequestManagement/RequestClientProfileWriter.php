<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\Models\Address;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Services\AddressService;
use App\Services\ContactService;
use Illuminate\Validation\ValidationException;

/**
 * Writes the client anagraphic block the work panel edits (spec 0049
 * amendment) onto the Registry's PersonalData card, reusing the owner-agnostic
 * ContactService/AddressService verbatim — the same path the Registries module
 * writes through, so the two never drift.
 *
 * Two deliberately DIFFERENT write semantics:
 *
 *  - contacts: authoritative sync (ContactService::sync). The panel loads the
 *    card's FULL contact set into its buffer, so what it submits is the whole
 *    truth and a removed row must really be deleted.
 *  - address: a single create-or-update row, never a sync. The panel only ever
 *    edits the card's PRIMARY address; a client may legitimately own others
 *    (registries allow many) and those must survive a request-panel save.
 *
 * The caller owns the surrounding transaction (RequestManagementService::
 * updateWork already opens one), so this writer never opens an outer one.
 */
final class RequestClientProfileWriter
{
    public function __construct(
        private readonly ContactService $contacts,
        private readonly AddressService $addresses,
    ) {}

    /**
     * @param  array<int, ContactInput>|null  $contacts
     *
     * @throws ValidationException when the client has no anagraphic card to write against
     */
    public function write(Opportunity $opportunity, ?array $contacts, ?AddressInput $address): void
    {
        if ($contacts === null && $address === null) {
            return;
        }

        $card = $this->resolveCard($opportunity, $contacts !== null ? 'client_contacts' : 'client_address');

        if ($contacts !== null) {
            $this->contacts->sync($card, $contacts);
        }

        if ($address !== null) {
            $this->writeAddress($card, $address);
        }
    }

    /**
     * The Registry's card, or a 422 keyed on the submitted block: without a
     * card there is no owner to attach contacts/addresses to (Registry itself
     * is not a valid contactable/addressable type).
     *
     * @throws ValidationException
     */
    private function resolveCard(Opportunity $opportunity, string $errorKey): PersonalData
    {
        $card = $opportunity->registry?->personalData;

        if ($card === null) {
            throw ValidationException::withMessages([
                $errorKey => 'The client has no anagraphic card to write to.',
            ]);
        }

        return $card;
    }

    /**
     * Create-or-update of the single edited address. An id that does not
     * belong to this card is treated as a create, never an update, mirroring
     * ContactService/AddressService::sync's own spoofed-id rule.
     */
    private function writeAddress(PersonalData $card, AddressInput $address): void
    {
        $existing = $address->id === null
            ? null
            : $card->addresses()->whereKey($address->id)->first();

        if ($existing === null) {
            $this->addresses->createFor($card, $address->data);

            return;
        }

        $this->addresses->update($existing, $this->preserveUnedited($existing, $address->data));
    }

    /**
     * AddressService::update replaces the row's attributes wholesale, but this
     * panel only edits the postal fields. Carry the dimensions it never shows
     * (primary flag, site type, coordinates) over from the persisted row, so a
     * save here can neither demote the owner's primary address (ADR 0010) nor
     * silently reset a registry's site type to the `billing` default.
     */
    private function preserveUnedited(Address $existing, CreateAddress $edited): CreateAddress
    {
        return new CreateAddress(
            line1: $edited->line1,
            line2: $edited->line2,
            postalCode: $edited->postalCode,
            cityId: $edited->cityId,
            provinceId: $edited->provinceId,
            stateId: $edited->stateId,
            countryId: $edited->countryId,
            latitude: $existing->latitude === null ? null : (string) $existing->latitude,
            longitude: $existing->longitude === null ? null : (string) $existing->longitude,
            isPrimary: (bool) $existing->is_primary,
            siteType: $existing->site_type,
        );
    }
}
