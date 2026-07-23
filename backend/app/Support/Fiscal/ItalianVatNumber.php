<?php

declare(strict_types=1);

namespace App\Support\Fiscal;

/**
 * Italian VAT number (partita IVA) algorithm: eleven digits whose last one is
 * a Luhn-style control digit. The same eleven-digit code is also the tax code
 * of a legal entity, so ItalianEntityTaxCode-style checks reuse this class.
 *
 * Pure and side-effect free. Mirrored 1:1 by the frontend twin in
 * `frontend/src/lib/fiscal/vat-number.ts`.
 */
final class ItalianVatNumber
{
    public const int LENGTH = 11;

    /** Uppercase, stripped of separators and of the optional IT country prefix. */
    public static function normalize(string $value): string
    {
        $digits = (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($value)));

        return str_starts_with($digits, 'IT') ? substr($digits, 2) : $digits;
    }

    /** Eleven digits with a valid control digit. */
    public static function isValid(string $value): bool
    {
        $code = self::normalize($value);

        // An all-zero code sums to zero and would otherwise pass the control
        // digit: it is never a real VAT number.
        if (preg_match('/^\d{11}$/', $code) !== 1 || $code === str_repeat('0', self::LENGTH)) {
            return false;
        }

        $sum = 0;

        for ($position = 0; $position < self::LENGTH; $position++) {
            $digit = (int) $code[$position];

            // Even (1-based) positions are doubled, then folded back below 10.
            if ($position % 2 === 1) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
