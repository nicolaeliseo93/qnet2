<?php

declare(strict_types=1);

namespace App\Support\Fiscal;

/**
 * Encodes a surname or a given name into the three-letter triple the Italian
 * tax code carries, so a submitted code can be checked against the anagraphic
 * fields of the same card.
 *
 * Pure and side-effect free. Mirrored 1:1 by the frontend twin in
 * `frontend/src/lib/fiscal/tax-code.ts`.
 */
final class TaxCodeNameEncoder
{
    private const int TRIPLE_LENGTH = 3;

    /** Filler used when the name yields fewer than three letters. */
    private const string FILLER = 'X';

    private const string VOWELS = 'AEIOU';

    /** Accented letters are folded to their base letter before encoding. */
    private const array DIACRITICS = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Ñ' => 'N',
    ];

    /** Consonants first, then vowels, padded with X. */
    public static function surname(string $surname): string
    {
        [$consonants, $vowels] = self::split($surname);

        return self::triple($consonants.$vowels);
    }

    /**
     * Same rule as the surname, except that a name with four or more
     * consonants drops the SECOND one (first, third and fourth are kept).
     */
    public static function name(string $name): string
    {
        [$consonants, $vowels] = self::split($name);

        if (strlen($consonants) >= 4) {
            return $consonants[0].$consonants[2].$consonants[3];
        }

        return self::triple($consonants.$vowels);
    }

    /**
     * @return array{0: string, 1: string} consonants and vowels, in the order they appear
     */
    private static function split(string $value): array
    {
        $letters = self::letters($value);
        $consonants = '';
        $vowels = '';

        foreach (str_split($letters) as $letter) {
            if (str_contains(self::VOWELS, $letter)) {
                $vowels .= $letter;
            } else {
                $consonants .= $letter;
            }
        }

        return [$consonants, $vowels];
    }

    private static function triple(string $letters): string
    {
        return substr(str_pad($letters, self::TRIPLE_LENGTH, self::FILLER), 0, self::TRIPLE_LENGTH);
    }

    /** Uppercase A-Z only: spaces, apostrophes and diacritics carry no code. */
    private static function letters(string $value): string
    {
        $upper = strtr(mb_strtoupper(trim($value)), self::DIACRITICS);

        return (string) preg_replace('/[^A-Z]/', '', $upper);
    }
}
