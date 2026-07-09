<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Types\Concerns\HandlesScalarStringField;
use App\Models\CustomFieldDefinition;

/**
 * Absolute URL: a single-line string validated with Laravel's `url` rule.
 * Rendered by the frontend `url` control (`<input type="url">`); the frontend
 * additionally scheme-checks before ever using the value as an `href`
 * (react-security.md safeUrl), since the browser does not.
 */
class UrlFieldType implements FieldTypeHandler
{
    use HandlesScalarStringField;

    public function key(): string
    {
        return 'url';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        return [$this->requiredOrNullable($definition), 'string', 'url', 'max:2048'];
    }
}
