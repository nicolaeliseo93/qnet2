<?php

declare(strict_types=1);

namespace App\Support\Fiscal;

/**
 * Italian personal tax code (codice fiscale) algorithms: format check, control
 * character verification, omocodia normalization and the decoding of the birth
 * date / gender the code encodes.
 *
 * Pure and side-effect free. Mirrored 1:1 by the frontend twin in
 * `frontend/src/lib/fiscal/tax-code.ts` — any change here changes that file too.
 */
final class ItalianTaxCode
{
    public const int LENGTH = 16;

    /**
     * The seven positions that carry a digit which omocodia may have replaced
     * with a letter (0-based): year, month day, and the last three of the
     * municipality code.
     */
    private const array OMOCODIA_POSITIONS = [6, 7, 9, 10, 12, 13, 14];

    /** Omocodia substitution table: the Nth letter stands for the digit N. */
    private const string OMOCODIA_LETTERS = 'LMNPQRSTUV';

    /** Month letters, January to December. */
    private const string MONTH_LETTERS = 'ABCDEHLMPRST';

    /** A female birth day is stored with 40 added to it. */
    private const int FEMALE_DAY_OFFSET = 40;

    /** Values of each character when it sits at an odd (1-based) position. */
    private const array ODD_VALUES = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9,
        '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9,
        'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11,
        'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    /** Uppercase, stripped of every separator the user may have typed. */
    public static function normalize(string $value): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($value)));
    }

    /** Structurally well formed AND carrying the right control character. */
    public static function isValid(string $value): bool
    {
        $code = self::normalize($value);

        if (preg_match('/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/', $code) !== 1) {
            return false;
        }

        return self::controlCharacter($code) === $code[15];
    }

    /**
     * Restores the digits an omocodia-corrected code hides behind letters, so
     * the encoded birth date stays readable. A code without omocodia is
     * returned unchanged.
     */
    public static function withoutOmocodia(string $code): string
    {
        foreach (self::OMOCODIA_POSITIONS as $position) {
            $digit = strpos(self::OMOCODIA_LETTERS, $code[$position]);

            if ($digit !== false) {
                $code[$position] = (string) $digit;
            }
        }

        return $code;
    }

    /**
     * The birth date encoded in the code, as [two-digit year, month, day] —
     * the century is NOT encoded, so the year is only comparable modulo 100.
     * Returns null when the encoded day is out of range.
     *
     * @return array{year: int, month: int, day: int}|null
     */
    public static function birthDate(string $code): ?array
    {
        $plain = self::withoutOmocodia(self::normalize($code));
        $month = strpos(self::MONTH_LETTERS, $plain[8]);

        if ($month === false) {
            return null;
        }

        $day = (int) substr($plain, 9, 2);

        if ($day > self::FEMALE_DAY_OFFSET) {
            $day -= self::FEMALE_DAY_OFFSET;
        }

        if ($day < 1 || $day > 31) {
            return null;
        }

        return [
            'year' => (int) substr($plain, 6, 2),
            'month' => $month + 1,
            'day' => $day,
        ];
    }

    /** True when the code encodes a female birth day (day + 40). */
    public static function isFemale(string $code): bool
    {
        $plain = self::withoutOmocodia(self::normalize($code));

        return (int) substr($plain, 9, 2) > self::FEMALE_DAY_OFFSET;
    }

    /** The surname triple the code carries (first three characters). */
    public static function surnameTriple(string $code): string
    {
        return substr(self::normalize($code), 0, 3);
    }

    /** The given-name triple the code carries (characters 4 to 6). */
    public static function nameTriple(string $code): string
    {
        return substr(self::normalize($code), 3, 3);
    }

    /** The control character computed from the first fifteen characters. */
    private static function controlCharacter(string $code): string
    {
        $sum = 0;

        for ($position = 0; $position < 15; $position++) {
            $character = $code[$position];

            // Positions are weighted by their 1-based parity: index 0 is odd.
            $sum += $position % 2 === 0
                ? self::ODD_VALUES[$character]
                : self::evenValue($character);
        }

        return chr(ord('A') + $sum % 26);
    }

    /** At an even (1-based) position a digit is worth itself and a letter its alphabet index. */
    private static function evenValue(string $character): int
    {
        return ctype_digit($character)
            ? (int) $character
            : ord($character) - ord('A');
    }
}
