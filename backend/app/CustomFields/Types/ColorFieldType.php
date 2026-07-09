<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Types\Concerns\HandlesScalarStringField;
use App\Models\CustomFieldDefinition;

/**
 * Color as a 6-digit hex string (`#RRGGBB`), the exact shape the HTML `color`
 * control emits. Rendered by the frontend `color` control
 * (`<input type="color">`).
 */
class ColorFieldType implements FieldTypeHandler
{
    use HandlesScalarStringField;

    public function key(): string
    {
        return 'color';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        return [$this->requiredOrNullable($definition), 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
    }
}
