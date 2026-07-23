<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Support\InputFormat;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Canonicalizes the user-typed identity/contact values of a personal-data card
 * BEFORE validation (user directive 2026-07-23), so every write surface stores
 * the same value for the same input regardless of how it was typed.
 *
 * Placed at the FormRequest boundary rather than in the Service because it must
 * run before the rules do: the `TaxCode` rule compares the code against the
 * card's first/last name, and a code typed lowercase or a name typed in caps
 * would otherwise be compared in a different shape than it is stored in.
 *
 * The card lives under a different key on each surface (root on the per-entity
 * endpoints, `personal_data` on the user endpoints, `client_identity` on the
 * request work panel), hence the nullable `$key` — the field list itself stays
 * single-sourced here.
 *
 * SPARSE-SAFE: a key that was not submitted is never introduced, and a non-string
 * value (null, array) is left untouched, so the "absent = leave alone" semantics
 * of every caller survive.
 *
 * @phpstan-require-extends FormRequest
 */
trait FormatsPersonalDataInput
{
    /**
     * Canonicalize the card's identity fields.
     *
     * @param  string|null  $key  the payload key holding the card, or null when it sits at the root
     */
    protected function formatIdentityInput(?string $key = null): void
    {
        $card = $key === null ? $this->all() : $this->input($key);

        if (! is_array($card)) {
            return;
        }

        $isCompany = ($card['type'] ?? null) === PersonalDataTypeEnum::Company->value;

        foreach (InputFormat::IDENTITY_FIELDS as $field) {
            if (isset($card[$field]) && is_string($card[$field])) {
                $card[$field] = InputFormat::identityField($field, $card[$field], $isCompany);
            }
        }

        $this->merge($key === null ? $card : [$key => $card]);
    }

    /**
     * Canonicalize the `value` of every row of a contact collection, each one
     * dispatched on its own `type` (a phone collapses to digits, an address to
     * lowercase).
     *
     * @param  string  $key  the payload key holding the rows (`contacts`, `personal_data.contacts`, `client_contacts`)
     */
    protected function formatContactRowsInput(string $key): void
    {
        $rows = $this->input($key);

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $index => $row) {
            if (! is_array($row) || ! isset($row['value']) || ! is_string($row['value'])) {
                continue;
            }

            $type = ContactTypeEnum::tryFrom((string) ($row['type'] ?? ''));

            // An unknown/missing type is a validation error of its own; formatting
            // it under a guessed channel would only mask it.
            if ($type !== null) {
                $rows[$index]['value'] = InputFormat::contactValue($type, $row['value']);
            }
        }

        $this->replaceInput($key, $rows);
    }

    /**
     * Canonicalize a single contact `value` sitting at the payload root
     * (POST/PUT /api/contacts), dispatched on the submitted `type`.
     */
    protected function formatContactInput(): void
    {
        $value = $this->input('value');
        $type = ContactTypeEnum::tryFrom((string) $this->input('type'));

        if (is_string($value) && $type !== null) {
            $this->merge(['value' => InputFormat::contactValue($type, $value)]);
        }
    }

    /**
     * Write back a nested collection: `merge()` only reaches top-level keys, so a
     * dotted key (`personal_data.contacts`) has to go through the full input array.
     *
     * @param  array<int|string, mixed>  $rows
     */
    private function replaceInput(string $key, array $rows): void
    {
        $all = $this->all();
        data_set($all, $key, $rows);

        $this->replace($all);
    }
}
