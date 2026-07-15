<?php

namespace App\Imports\Leads;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;

/**
 * Resolves the id of an EXISTING Referent whose personal-data card owns a
 * Contact matching one of a staged row's email/phone/mobile values (spec
 * 0033 decision: "match su un Referent esistente per email/telefono/
 * cellulare"). Backs `LeadsImportDefinition::resolveDuplicate()`.
 *
 * Values are compared NORMALIZED (case/whitespace for email, digits-only for
 * phone/mobile) rather than via a raw SQL LIKE/collation trick, mirroring
 * GeoResolver::findByName()/CompaniesImportDefinition::existsInDatabase() —
 * fetch the (bounded) candidate set via Eloquent, compare in PHP, never
 * interpolate the row's value into SQL.
 */
final class LeadDuplicateMatcher
{
    /**
     * The row's dominant Referent match, or null when none of its declared
     * contact values matches an existing one.
     *
     * @param  array<string, mixed>  $mapped  field id => resolved value (after recognizers)
     */
    public function match(array $mapped): ?int
    {
        $targets = $this->normalizedTargets($mapped);

        if ($targets === []) {
            return null;
        }

        $contacts = Contact::query()
            ->where('contactable_type', (new PersonalData)->getMorphClass())
            ->whereIn('type', array_map(
                static fn (ContactTypeEnum $type): string => $type->value,
                array_values(LeadContactFields::map()),
            ))
            ->get(['id', 'type', 'value', 'contactable_id']);

        foreach ($contacts as $contact) {
            /** @var ContactTypeEnum $type */
            $type = $contact->type;
            $normalizedValue = $this->normalize($type, (string) $contact->value);

            if (! in_array($normalizedValue, $targets[$type->value] ?? [], true)) {
                continue;
            }

            $referentId = $this->referentIdForCard((int) $contact->contactable_id);

            if ($referentId !== null) {
                return $referentId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, array<int, string>> contact type value => normalized candidate values
     */
    private function normalizedTargets(array $mapped): array
    {
        $targets = [];

        foreach (LeadContactFields::map() as $field => $type) {
            $value = trim((string) ($mapped[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            $targets[$type->value][] = $this->normalize($type, $value);
        }

        return $targets;
    }

    private function normalize(ContactTypeEnum $type, string $value): string
    {
        $trimmed = trim($value);

        return $type === ContactTypeEnum::Email
            ? mb_strtolower($trimmed)
            : (string) preg_replace('/[^0-9+]/', '', $trimmed);
    }

    /**
     * The Referent id owning the given personal-data card, or null when the
     * card belongs to a different owner (e.g. a User) — a leads import can
     * only ever match a Referent.
     */
    private function referentIdForCard(int $cardId): ?int
    {
        /** @var PersonalData|null $card */
        $card = PersonalData::query()->find($cardId, ['id', 'personable_type', 'personable_id']);

        if ($card === null || $card->personable_type !== (new Referent)->getMorphClass()) {
            return null;
        }

        return (int) $card->personable_id;
    }
}
