<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use Closure;

/**
 * Shared "value sits on the step grid starting at min" validation, used by
 * IntegerFieldType and DecimalFieldType's `config.step`.
 */
trait ValidatesNumericStep
{
    private function stepRule(float $step, float $min): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($step, $min): void {
            if (! is_numeric($value)) {
                return;
            }

            $steps = ((float) $value - $min) / $step;

            if (abs($steps - round($steps)) > 1e-6) {
                $fail("The :attribute must be a multiple of {$step}.");
            }
        };
    }
}
