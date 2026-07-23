<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Fiscal\ItalianVatNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an Italian VAT number (partita IVA): eleven digits with a valid
 * control digit, with the optional `IT` country prefix tolerated. A blank
 * value passes — whether the field is required is the FormRequest's call.
 */
final class VatNumber implements ValidationRule
{
    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (! ItalianVatNumber::isValid($value)) {
            $fail(__('The VAT number is not valid.'));
        }
    }
}
