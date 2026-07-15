<?php

namespace App\Imports\Recognition;

use App\Imports\ImportRowContext;
use Illuminate\Support\Str;

/**
 * Splits a mapped `full_name` field into `first_name`/`last_name` for rows
 * that carry a single name column instead of two (spec 0033 AC-004, leads
 * import). Runs during StageImportJob for any definition listing this class
 * in recognizers().
 *
 * Heuristic (compact by design — no first-name/surname database, per
 * engineering.md anti-abstraction-bloat):
 *   - first_name OR last_name already mapped (non-blank) -> left untouched
 *     entirely, so a value the user (or a prior recognizer run, on revise)
 *     already set is never overwritten by a fresh full_name split.
 *   - 1 token   -> treated as the first name (the common case: a single word
 *     is far more often a given name than a surname-only entry); last_name
 *     is left blank for StagedRowBuilder's placeholder step to flag for
 *     review, rather than guessing a surname here.
 *   - 2 tokens  -> `[first, last]` order (the common CSV export convention;
 *     "Mario Rossi" and "Rossi Mario" both split token0/token1 — telling
 *     first-name-first from surname-first apart needs a name dictionary,
 *     which is out of scope, so both are treated the same, deterministically).
 *   - 3+ tokens -> a leading surname particle ("de", "dal", "lo", ...) starts
 *     a compound surname ("Anna De Santis" -> first "Anna", last "De Santis");
 *     otherwise the LAST token is the surname and everything before it is a
 *     (possibly compound) first name ("Maria Teresa Rossi" -> first
 *     "Maria Teresa", last "Rossi").
 */
final class NameSplitRecognizer implements RowRecognizer
{
    public const string FULL_NAME_FIELD = 'full_name';

    public const string FIRST_NAME_FIELD = 'first_name';

    public const string LAST_NAME_FIELD = 'last_name';

    /**
     * Surname particles that, found before the trailing token(s), start a
     * compound surname rather than belonging to a compound first name.
     *
     * @var array<int, string>
     */
    private const array SURNAME_PARTICLES = [
        'de', 'di', 'del', 'della', 'dei', 'degli', 'dal', 'dalla',
        'lo', 'la', 'le', 'van', 'von', 'mac', 'mc',
    ];

    public function recognize(ImportRowContext $context, array $mapped): RecognitionResult
    {
        // Step 1: nothing to do without a full_name value, or when first/last
        // are already explicitly mapped — never overwrite a direct mapping.
        $fullName = trim((string) ($mapped[self::FULL_NAME_FIELD] ?? ''));

        if ($fullName === '' || $this->alreadyMapped($mapped)) {
            return RecognitionResult::none();
        }

        // Step 2: split according to the token-count heuristic.
        $tokens = $this->tokenize($fullName);

        return match (count($tokens)) {
            1 => $this->splitSingleToken($tokens[0]),
            2 => RecognitionResult::resolved([
                self::FIRST_NAME_FIELD => $tokens[0],
                self::LAST_NAME_FIELD => $tokens[1],
            ]),
            default => $this->splitMultiToken($tokens),
        };
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function alreadyMapped(array $mapped): bool
    {
        return $this->present($mapped, self::FIRST_NAME_FIELD) || $this->present($mapped, self::LAST_NAME_FIELD);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function present(array $mapped, string $field): bool
    {
        return trim((string) ($mapped[$field] ?? '')) !== '';
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $fullName): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', $fullName) ?: [],
            static fn (string $token): bool => $token !== '',
        ));
    }

    private function splitSingleToken(string $token): RecognitionResult
    {
        // The token is assigned to first_name; last_name is left blank for
        // StagedRowBuilder's placeholder step to flag (spec 0033 delta
        // D-2026-07-15-placeholder-review-fields) — a single actionable
        // warning there, instead of a redundant one here.
        return RecognitionResult::resolved([
            self::FIRST_NAME_FIELD => $token,
            self::LAST_NAME_FIELD => null,
        ]);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function splitMultiToken(array $tokens): RecognitionResult
    {
        $particleIndex = $this->findSurnameParticle($tokens);

        if ($particleIndex !== null) {
            return RecognitionResult::resolved([
                self::FIRST_NAME_FIELD => implode(' ', array_slice($tokens, 0, $particleIndex)),
                self::LAST_NAME_FIELD => implode(' ', array_slice($tokens, $particleIndex)),
            ]);
        }

        return RecognitionResult::resolved([
            self::FIRST_NAME_FIELD => implode(' ', array_slice($tokens, 0, -1)),
            self::LAST_NAME_FIELD => $tokens[count($tokens) - 1],
        ]);
    }

    /**
     * Index of the first token (strictly between the leading first-name token
     * and the trailing surname token) matching a known surname particle, or
     * null when none is found. A particle can never start at index 0 (a
     * surname needs at least one token after it) nor be the last token.
     *
     * @param  array<int, string>  $tokens
     */
    private function findSurnameParticle(array $tokens): ?int
    {
        $lastIndex = count($tokens) - 1;

        for ($i = 1; $i < $lastIndex; $i++) {
            if (in_array(Str::lower($tokens[$i]), self::SURNAME_PARTICLES, true)) {
                return $i;
            }
        }

        return null;
    }
}
