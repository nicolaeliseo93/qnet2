<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Types\Concerns\HandlesScalarStringField;
use App\Models\CustomFieldDefinition;

/**
 * Time of day, no date component. Stored as `H:i` (or `H:i:s`) — both shapes
 * the HTML `time` control can emit are accepted. Rendered by the frontend
 * `time` control (`<input type="time">`).
 */
class TimeFieldType implements FieldTypeHandler
{
    use HandlesScalarStringField;

    public function key(): string
    {
        return 'time';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        return [$this->requiredOrNullable($definition), 'string', 'date_format:H:i,H:i:s'];
    }
}
