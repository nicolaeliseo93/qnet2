<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Types\Concerns\HandlesScalarStringField;
use App\Models\CustomFieldDefinition;

/**
 * Date + time of day. Stored as an ISO-local string; both the seconds-less
 * shape emitted by the HTML `datetime-local` control (`Y-m-d\TH:i`) and the
 * seconds-bearing shape are accepted. Rendered by the frontend `datetime`
 * control (`<input type="datetime-local">`).
 */
class DateTimeFieldType implements FieldTypeHandler
{
    use HandlesScalarStringField;

    public function key(): string
    {
        return 'datetime';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        return [$this->requiredOrNullable($definition), 'string', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'];
    }
}
