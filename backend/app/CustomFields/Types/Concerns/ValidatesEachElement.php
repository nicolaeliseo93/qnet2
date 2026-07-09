<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use Closure;

/**
 * Per-element validation for a multi-valued field (enum multiselect, relation
 * many), as a single Closure rule attached directly to the array attribute.
 *
 * NOT implemented via Illuminate\Validation\Rule::forEach: in this Laravel
 * version, a colon-parametrized sub-rule (`in:...`, `exists:...`) compiled by
 * Rule::forEach and placed alongside a sibling rule (e.g. `array`) on the
 * SAME attribute gets nested one level too deep by
 * ValidationRuleParser::explodeExplicitRule() and is never correctly
 * re-flattened, so it fails with a bogus "validateIn:... does not exist"
 * BadMethodCallException — reproducible standalone, unrelated to this
 * feature. A single Closure rule validating every element up front sidesteps
 * the bug entirely and is exactly as expressive for our MVP needs.
 */
trait ValidatesEachElement
{
    /**
     * @param  callable(mixed): bool  $isValid
     */
    private function eachElementRule(callable $isValid): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($isValid): void {
            foreach ((array) $value as $item) {
                if (! $isValid($item)) {
                    $fail("The {$attribute} contains an invalid value.");

                    return;
                }
            }
        };
    }
}
