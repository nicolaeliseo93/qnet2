<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Types\Concerns\HandlesScalarStringField;
use App\Models\CustomFieldDefinition;

/**
 * Calendar date, no time component. Stored as an ISO `Y-m-d` string (which
 * also sorts correctly lexicographically, so OrdersByJsonPath needs no special
 * casing). Rendered by the frontend `date` control (`<input type="date">`).
 */
class DateFieldType implements FieldTypeHandler
{
    use HandlesScalarStringField;

    public function key(): string
    {
        return 'date';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        return [$this->requiredOrNullable($definition), 'string', 'date_format:Y-m-d'];
    }
}
