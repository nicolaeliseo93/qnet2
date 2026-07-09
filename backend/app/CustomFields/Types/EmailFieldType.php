<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Types\Concerns\HandlesScalarStringField;
use App\Models\CustomFieldDefinition;

/**
 * Email address: a single-line string validated with Laravel's `email` rule.
 * Rendered by the frontend `email` control (`<input type="email">`).
 */
class EmailFieldType implements FieldTypeHandler
{
    use HandlesScalarStringField;

    public function key(): string
    {
        return 'email';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        return [$this->requiredOrNullable($definition), 'string', 'email', 'max:191'];
    }
}
