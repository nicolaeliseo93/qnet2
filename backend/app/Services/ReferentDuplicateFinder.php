<?php

namespace App\Services;

use App\DataObjects\Referents\ReferentDuplicateCriteria;
use App\DataObjects\Referents\ReferentDuplicateMatch;
use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Support\ContactValueNormalizer;

/**
 * Finds EXISTING Referents whose contacts (email/phone/mobile) or
 * `personal_data.tax_code` collide with the given criteria (spec 0037),
 * backing the live, non-blocking duplicate check on the referent create
 * form. Shares its normalization semantics with `LeadDuplicateMatcher`
 * (spec 0033/0036) via `ContactValueNormalizer`, but queries per channel
 * with bound `whereRaw` for the deterministic transforms (email/tax_code
 * case) instead of hydrating a whole contact type, since this runs on every
 * debounced keystroke rather than once per import row.
 */
final class ReferentDuplicateFinder
{
    private const int MAX_MATCHES = 5;

    /** Canonical, deterministic order for `ReferentDuplicateMatch::$matchedOn`. */
    private const array MATCH_ORDER = ['email', 'phone', 'mobile', 'tax_code'];

    /**
     * @return array<int, ReferentDuplicateMatch>
     */
    public function find(ReferentDuplicateCriteria $criteria): array
    {
        // Step 1: normalize every criterion into its comparable target(s).
        $contactTargets = $this->contactTargets($criteria->contacts);
        $taxCodeTarget = $this->normalizedTaxCode($criteria->taxCode);

        // Step 2: personal_data.id => matched channel(s), via targeted
        // per-type contact queries (never a scan across unrelated types).
        $channelsByReferentId = $this->resolveReferents($this->collectContactMatches($contactTargets));

        // Step 3: the tax_code query is already scoped to Referent-owned
        // cards, so it yields referent ids directly — merge them in.
        if ($taxCodeTarget !== null) {
            foreach ($this->matchTaxCode($taxCodeTarget) as $referentId) {
                $channelsByReferentId[$referentId] = $this->mergeChannel($channelsByReferentId[$referentId] ?? [], 'tax_code');
            }
        }

        // Step 4: cap to 5, order by id desc, hydrate the display name.
        return $this->buildMatches($channelsByReferentId);
    }

    /**
     * @param  array<int, array{type: string, value: string}>  $contacts
     * @return array<string, array<int, string>> ContactTypeEnum value => normalized targets
     */
    private function contactTargets(array $contacts): array
    {
        $targets = [];

        foreach ($contacts as $contact) {
            $type = ContactTypeEnum::tryFrom((string) ($contact['type'] ?? ''));
            $value = trim((string) ($contact['value'] ?? ''));

            if ($type === null || $value === '' || ! in_array($type, [ContactTypeEnum::Email, ContactTypeEnum::Phone, ContactTypeEnum::Mobile], true)) {
                continue;
            }

            $targets[$type->value][] = ContactValueNormalizer::contact($type, $value);
        }

        return array_map(
            static fn (array $values): array => array_values(array_unique($values)),
            $targets,
        );
    }

    private function normalizedTaxCode(?string $taxCode): ?string
    {
        $value = trim((string) $taxCode);

        return $value === '' ? null : ContactValueNormalizer::taxCode($value);
    }

    /**
     * @param  array<string, array<int, string>>  $contactTargets
     * @return array<int, array<int, string>> personal_data.id => channels
     */
    private function collectContactMatches(array $contactTargets): array
    {
        $morph = (new PersonalData)->getMorphClass();
        $channelsByCardId = [];

        // Email: a single deterministic transform (LOWER), matched directly
        // in SQL with bound placeholders.
        if (($contactTargets[ContactTypeEnum::Email->value] ?? []) !== []) {
            foreach ($this->matchEmail($contactTargets[ContactTypeEnum::Email->value], $morph) as $cardId) {
                $channelsByCardId[$cardId] = $this->mergeChannel($channelsByCardId[$cardId] ?? [], ContactTypeEnum::Email->value);
            }
        }

        // Phone/mobile: formatting varies too much for a portable SQL
        // transform, so fetch the bounded per-type candidate set and
        // normalize in PHP (mirrors LeadDuplicateMatcher).
        foreach ([ContactTypeEnum::Phone, ContactTypeEnum::Mobile] as $type) {
            if (($contactTargets[$type->value] ?? []) === []) {
                continue;
            }

            foreach ($this->matchPhoneLike($type, $contactTargets[$type->value], $morph) as $cardId) {
                $channelsByCardId[$cardId] = $this->mergeChannel($channelsByCardId[$cardId] ?? [], $type->value);
            }
        }

        return $channelsByCardId;
    }

    /**
     * @param  array<int, string>  $targets
     * @return array<int, int> distinct personal_data.id (Contact::contactable_id)
     */
    private function matchEmail(array $targets, string $morph): array
    {
        $placeholders = implode(',', array_fill(0, count($targets), '?'));

        return Contact::query()
            ->where('contactable_type', $morph)
            ->where('type', ContactTypeEnum::Email->value)
            ->whereRaw("LOWER(value) IN ({$placeholders})", $targets)
            ->pluck('contactable_id')
            ->unique()
            ->all();
    }

    /**
     * @param  array<int, string>  $targets
     * @return array<int, int> distinct personal_data.id (Contact::contactable_id)
     */
    private function matchPhoneLike(ContactTypeEnum $type, array $targets, string $morph): array
    {
        return Contact::query()
            ->where('contactable_type', $morph)
            ->where('type', $type->value)
            ->get(['value', 'contactable_id'])
            ->filter(fn (Contact $contact): bool => in_array(
                ContactValueNormalizer::contact($type, (string) $contact->value),
                $targets,
                true,
            ))
            ->pluck('contactable_id')
            ->unique()
            ->all();
    }

    /**
     * @return array<int, int> Referent ids (personal_data.personable_id)
     */
    private function matchTaxCode(string $target): array
    {
        return PersonalData::query()
            ->where('personable_type', (new Referent)->getMorphClass())
            ->whereNotNull('tax_code')
            ->whereRaw('UPPER(tax_code) = ?', [$target])
            ->pluck('personable_id')
            ->unique()
            ->all();
    }

    /**
     * Resolves matched personal_data.id cards to their owning Referent,
     * excluding cards owned by anything else (e.g. a User) — a duplicate
     * check can only ever match a Referent.
     *
     * @param  array<int, array<int, string>>  $channelsByCardId
     * @return array<int, array<int, string>> Referent id => channels
     */
    private function resolveReferents(array $channelsByCardId): array
    {
        if ($channelsByCardId === []) {
            return [];
        }

        $cards = PersonalData::query()
            ->where('personable_type', (new Referent)->getMorphClass())
            ->whereIn('id', array_keys($channelsByCardId))
            ->get(['id', 'personable_id']);

        $channelsByReferentId = [];

        foreach ($cards as $card) {
            $referentId = (int) $card->personable_id;

            foreach ($channelsByCardId[$card->id] as $channel) {
                $channelsByReferentId[$referentId] = $this->mergeChannel($channelsByReferentId[$referentId] ?? [], $channel);
            }
        }

        return $channelsByReferentId;
    }

    /**
     * @param  array<int, string>  $channels
     * @return array<int, string>
     */
    private function mergeChannel(array $channels, string $channel): array
    {
        return in_array($channel, $channels, true) ? $channels : [...$channels, $channel];
    }

    /**
     * @param  array<int, array<int, string>>  $channelsByReferentId
     * @return array<int, ReferentDuplicateMatch>
     */
    private function buildMatches(array $channelsByReferentId): array
    {
        if ($channelsByReferentId === []) {
            return [];
        }

        $ids = array_keys($channelsByReferentId);
        rsort($ids, SORT_NUMERIC);
        $topIds = array_slice($ids, 0, self::MAX_MATCHES);

        $names = Referent::query()->whereIn('id', $topIds)->pluck('name', 'id');

        return array_map(
            fn (int $id): ReferentDuplicateMatch => new ReferentDuplicateMatch(
                referentId: $id,
                name: (string) ($names[$id] ?? ''),
                matchedOn: array_values(array_intersect(self::MATCH_ORDER, $channelsByReferentId[$id])),
            ),
            $topIds,
        );
    }
}
