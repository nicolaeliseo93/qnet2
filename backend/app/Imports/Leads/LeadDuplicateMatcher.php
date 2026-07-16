<?php

namespace App\Imports\Leads;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\PersonalData;
use App\Models\Referent;

/**
 * Resolves the EXISTING Referent a staged row collides with, by email/phone/
 * mobile (spec 0033 decision) or, additionally (spec 0036), by
 * `personal_data.tax_code`. Values are compared NORMALIZED (case/whitespace
 * for email/tax_code, digits-only for phone/mobile) rather than via a raw SQL
 * LIKE/collation trick, mirroring GeoResolver::findByName()/
 * CompaniesImportDefinition::existsInDatabase() — fetch the (bounded)
 * candidate set via Eloquent, compare in PHP, never interpolate the row's
 * value into SQL. Backs `LeadsImportDefinition::resolveDuplicate()`/
 * `resolveDuplicateMatch()`.
 */
final class LeadDuplicateMatcher
{
    /** Canonical, deterministic order for `LeadDuplicateMatch::$matchedOn`. */
    private const array MATCH_ORDER = ['email', 'phone', 'mobile', 'tax_code'];

    /**
     * The row's dominant Referent match — id, display name, and every
     * channel that matches it, cumulative — or null when nothing matches.
     *
     * @param  array<string, mixed>  $mapped  field id => resolved value (after recognizers)
     */
    public function match(array $mapped): ?LeadDuplicateMatch
    {
        // Step 1: an email/phone/mobile Contact match takes priority
        // (unchanged pre-0036 lookup); tax_code is tried only when no
        // contact channel hits, keeping the existing semantics intact.
        $referentId = $this->matchByContact($mapped) ?? $this->matchByTaxCode($mapped);

        if ($referentId === null) {
            return null;
        }

        // Step 2: report every channel that ALSO matches the winning
        // referent (cumulative), not just whichever one found it first.
        return $this->buildMatch($referentId, $mapped);
    }

    /**
     * The id of the Lead already tying the given Referent to the run's
     * campaign, or null when none exists (either no lead at all, or only on
     * a DIFFERENT campaign) — spec 0036 AC-002.
     *
     * @param  array<string, mixed>  $globalConfig
     */
    public function existingLeadId(int $referentId, array $globalConfig): ?int
    {
        $campaignId = $this->campaignId($globalConfig);

        if ($campaignId === null) {
            return null;
        }

        /** @var int|null $leadId */
        $leadId = Lead::query()
            ->where('referent_id', $referentId)
            ->where('campaign_id', $campaignId)
            ->value('id');

        return $leadId;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function matchByContact(array $mapped): ?int
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
     */
    private function matchByTaxCode(array $mapped): ?int
    {
        $target = $this->normalizedTaxCode($mapped);

        if ($target === null) {
            return null;
        }

        $cards = PersonalData::query()
            ->where('personable_type', (new Referent)->getMorphClass())
            ->whereNotNull('tax_code')
            ->get(['id', 'tax_code', 'personable_id']);

        foreach ($cards as $card) {
            if ($this->normalizeTaxCode((string) $card->tax_code) === $target) {
                return (int) $card->personable_id;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function buildMatch(int $referentId, array $mapped): LeadDuplicateMatch
    {
        /** @var Referent|null $referent */
        $referent = Referent::query()->with('personalData.contacts')->find($referentId);

        return new LeadDuplicateMatch(
            referentId: $referentId,
            referentName: $referent?->name ?? '',
            matchedOn: $this->matchedOn($referent?->personalData, $mapped),
        );
    }

    /**
     * Every channel of the given card that matches the row's OWN target
     * values, in the canonical MATCH_ORDER — the cumulative "matched_on" set.
     *
     * @param  array<string, mixed>  $mapped
     * @return array<int, string>
     */
    private function matchedOn(?PersonalData $card, array $mapped): array
    {
        if ($card === null) {
            return [];
        }

        $targets = $this->normalizedTargets($mapped);
        $taxTarget = $this->normalizedTaxCode($mapped);
        $matched = [];

        foreach ($card->contacts as $contact) {
            /** @var ContactTypeEnum $type */
            $type = $contact->type;

            if (in_array($type->value, $matched, true)) {
                continue;
            }

            if (in_array($this->normalize($type, (string) $contact->value), $targets[$type->value] ?? [], true)) {
                $matched[] = $type->value;
            }
        }

        if ($taxTarget !== null && $card->tax_code !== null && $this->normalizeTaxCode((string) $card->tax_code) === $taxTarget) {
            $matched[] = 'tax_code';
        }

        return array_values(array_intersect(self::MATCH_ORDER, $matched));
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

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function normalizedTaxCode(array $mapped): ?string
    {
        $value = trim((string) ($mapped['tax_code'] ?? ''));

        return $value === '' ? null : $this->normalizeTaxCode($value);
    }

    private function normalizeTaxCode(string $value): string
    {
        return mb_strtoupper(trim($value));
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

    /**
     * @param  array<string, mixed>  $globalConfig
     */
    private function campaignId(array $globalConfig): ?int
    {
        $value = $globalConfig['campaign_id'] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }
}
