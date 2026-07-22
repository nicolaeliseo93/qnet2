<?php

namespace App\Notes\Mentions;

/**
 * Pure parsing of the @mention token format stored in Note::body (spec 0052,
 * D-12): `@[Name Surname](user:12)`. The single place both directions of the
 * D-12 invariant (server-verified token/mentions coherence) and the
 * notification excerpt (token -> "@Name") rely on.
 */
final class MentionParser
{
    private const string TOKEN_PATTERN = '/@\[([^\]]*)\]\(user:(\d+)\)/';

    /**
     * User ids embedded in $body's tokens, in order of first appearance,
     * deduplicated (a token repeated for the same user counts once, D-12).
     *
     * @return array<int, int>
     */
    public static function extractIds(string $body): array
    {
        preg_match_all(self::TOKEN_PATTERN, $body, $matches);

        $ids = array_map('intval', $matches[2] ?? []);

        return array_values(array_unique($ids));
    }

    /**
     * Replace every token with "@{name}", preferring $namesById and falling
     * back to the name embedded in the token itself when the id is unknown.
     *
     * @param  array<int, string>  $namesById
     */
    public static function resolveTokens(string $body, array $namesById): string
    {
        $resolved = preg_replace_callback(
            self::TOKEN_PATTERN,
            static fn (array $matches): string => '@'.($namesById[(int) $matches[2]] ?? $matches[1]),
            $body,
        );

        return $resolved ?? $body;
    }
}
