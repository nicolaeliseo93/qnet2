<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\Enums\ContactTypeEnum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation + DTO assembly for the client anagraphic block the work panel
 * writes back (spec 0049 amendment): `client_contacts` and `client_address`,
 * both landing on the Registry's PersonalData card.
 *
 * Deliberately NOT `ValidatesUserProfile`: that trait also owns the card's
 * IDENTITY fields (type/first_name/company_name/...), which this module must
 * never touch — `registries.name` is derived from them. Only the two child
 * collections are writable here, so the rules are their exact subset and
 * reuse the SAME rule sources (ContactTypeEnum, geo `exists`) to avoid drift.
 *
 * Sparse semantics, consistent with the rest of the PATCH payload: an absent
 * key leaves that side untouched. `client_contacts` present (even empty) is
 * AUTHORITATIVE — it is synced against the card's full contact set.
 * `client_address` is a single create-or-update row, never an authoritative
 * sync: the client's other addresses are out of this panel's reach.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesRequestClientProfile
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function clientProfileRules(): array
    {
        $hasAddress = $this->has('client_address') && $this->input('client_address') !== null;

        return [
            'client_contacts' => ['sometimes', 'array'],
            'client_contacts.*.id' => ['sometimes', 'integer', 'min:1'],
            'client_contacts.*.type' => ['required', Rule::enum(ContactTypeEnum::class)],
            // The per-type `value` rules run in validateClientProfile() (they
            // depend on each row's own type); here only the base shape.
            'client_contacts.*.value' => ['required', 'string', 'max:255'],
            'client_contacts.*.label' => ['nullable', 'string', 'max:255'],
            'client_contacts.*.is_primary' => ['sometimes', 'boolean'],

            'client_address' => ['sometimes', 'nullable', 'array'],
            'client_address.id' => ['sometimes', 'integer', 'min:1'],
            'client_address.line1' => [Rule::requiredIf($hasAddress), 'string', 'max:255'],
            'client_address.line2' => ['nullable', 'string', 'max:255'],
            'client_address.postal_code' => ['nullable', 'string', 'max:20'],
            'client_address.city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'client_address.province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'client_address.state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'client_address.country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
        ];
    }

    /**
     * Applies the per-type contact `value` rules, reusing
     * ContactTypeEnum::valueRules() exactly like ValidatesUserProfile, with
     * errors mapped to `client_contacts.{i}.value`.
     */
    protected function validateClientProfile(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        /** @var array<int, mixed> $rows */
        $rows = (array) $this->input('client_contacts', []);

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $valueRules = ContactTypeEnum::tryFrom((string) ($row['type'] ?? ''))?->valueRules() ?? [];

            if ($valueRules === []) {
                continue;
            }

            $rowValidator = validator(['value' => $row['value'] ?? null], ['value' => $valueRules]);

            foreach ($rowValidator->errors()->get('value') as $message) {
                $validator->errors()->add("client_contacts.{$index}.value", $message);
            }
        }
    }

    /**
     * The typed, sparse client-profile slice of the PATCH payload: a key is
     * present only when it was submitted, so the service keeps its
     * array_key_exists() semantics for these two as for every other key.
     *
     * @return array{client_contacts?: array<int, ContactInput>, client_address?: AddressInput}
     */
    public function clientProfilePayload(): array
    {
        $payload = [];

        if ($this->has('client_contacts')) {
            $payload['client_contacts'] = $this->buildClientContacts();
        }

        if ($this->has('client_address') && $this->input('client_address') !== null) {
            $payload['client_address'] = $this->buildClientAddress();
        }

        return $payload;
    }

    /**
     * @return array<int, ContactInput>
     */
    private function buildClientContacts(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = (array) $this->input('client_contacts', []);

        return array_values(array_map(
            fn (array $row): ContactInput => new ContactInput(
                id: isset($row['id']) ? (int) $row['id'] : null,
                data: new CreateContact(
                    type: ContactTypeEnum::from((string) ($row['type'] ?? '')),
                    value: (string) ($row['value'] ?? ''),
                    label: $row['label'] ?? null,
                    isPrimary: (bool) ($row['is_primary'] ?? false),
                ),
            ),
            $rows,
        ));
    }

    private function buildClientAddress(): AddressInput
    {
        /** @var array<string, mixed> $row */
        $row = (array) $this->input('client_address', []);

        return new AddressInput(
            id: isset($row['id']) ? (int) $row['id'] : null,
            data: new CreateAddress(
                line1: (string) ($row['line1'] ?? ''),
                line2: $row['line2'] ?? null,
                postalCode: $row['postal_code'] ?? null,
                cityId: isset($row['city_id']) ? (int) $row['city_id'] : null,
                provinceId: isset($row['province_id']) ? (int) $row['province_id'] : null,
                stateId: isset($row['state_id']) ? (int) $row['state_id'] : null,
                countryId: isset($row['country_id']) ? (int) $row['country_id'] : null,
            ),
        );
    }
}
