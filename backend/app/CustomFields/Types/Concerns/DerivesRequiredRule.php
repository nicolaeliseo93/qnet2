<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use App\Models\CustomFieldDefinition;

/**
 * Shared required/nullable base rule, derived from the definition's generic
 * `validation.required` flag (spec 0021 data_contract validation object).
 */
trait DerivesRequiredRule
{
    private function isRequired(CustomFieldDefinition $definition): bool
    {
        return (bool) ($definition->validation['required'] ?? false);
    }

    private function requiredOrNullable(CustomFieldDefinition $definition): string
    {
        return $this->isRequired($definition) ? 'required' : 'nullable';
    }
}
