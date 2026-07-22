<?php

namespace App\Notes\Mentions;

use App\Notes\NoteEntityRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Enforces both mention invariants server-side (spec 0052): the D-12
 * token/mentions coherence (the client can neither notify without
 * mentioning in the text nor mention without notifying) and the D-10
 * mentionable-set boundary (a mention outside the set is rejected even if
 * the body contains a matching token) — AC-051/AC-052. Used identically by
 * create and update: mentions are always re-validated against the CURRENT
 * body and the CURRENT mentionable set.
 */
final class MentionValidator
{
    public function __construct(private readonly NoteEntityRegistry $registry) {}

    /**
     * @param  array<int, int>  $mentionIds
     *
     * @throws ValidationException
     */
    public function validate(string $entityType, Model $record, string $body, array $mentionIds): void
    {
        $tokenIds = MentionParser::extractIds($body);
        $mentionIds = array_values(array_unique($mentionIds));

        $sortedTokenIds = $tokenIds;
        $sortedMentionIds = $mentionIds;
        sort($sortedTokenIds);
        sort($sortedMentionIds);

        if ($sortedTokenIds !== $sortedMentionIds) {
            throw ValidationException::withMessages([
                'mentions' => ['The mentions must match the @mentions in the note body exactly.'],
            ]);
        }

        if ($mentionIds === []) {
            return;
        }

        $allowedIds = $this->registry->mentionableUsersQuery($entityType, $record)
            ->whereIn('id', $mentionIds)
            ->pluck('id')
            ->all();

        if (count($allowedIds) !== count($mentionIds)) {
            throw ValidationException::withMessages([
                'mentions' => ['One or more mentioned users cannot be mentioned on this record.'],
            ]);
        }
    }
}
