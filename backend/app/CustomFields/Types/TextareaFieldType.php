<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Types\Concerns\AppliesTextFilter;
use App\CustomFields\Types\Concerns\DerivesRequiredRule;
use App\CustomFields\Types\Concerns\OrdersByJsonPath;
use App\CustomFields\Types\Concerns\ResolvesDistinctJsonValues;
use App\CustomFields\Types\Concerns\ResolvesJsonColumn;
use App\Models\CustomFieldDefinition;

/**
 * Multi-line free text (spec 0021 MVP). `config`: rows (frontend rendering
 * hint only, unused server-side), maxLength.
 */
class TextareaFieldType implements FieldTypeHandler
{
    use AppliesTextFilter;
    use DerivesRequiredRule;
    use OrdersByJsonPath;
    use ResolvesDistinctJsonValues;
    use ResolvesJsonColumn;

    public function key(): string
    {
        return 'textarea';
    }

    public function storageType(): string
    {
        return 'string';
    }

    public function columnType(): string
    {
        return 'text';
    }

    public function filterType(): string
    {
        return 'text';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        $config = $definition->config ?? [];
        $rules = [$this->requiredOrNullable($definition), 'string'];

        if (isset($config['maxLength'])) {
            $rules[] = "max:{$config['maxLength']}";
        }

        return $rules;
    }

    public function normalizeForStore(mixed $value, CustomFieldDefinition $definition): mixed
    {
        return $value === null ? null : (string) $value;
    }

    public function resolveForRead(mixed $stored, CustomFieldDefinition $definition): mixed
    {
        return $stored;
    }

    public function toMeta(CustomFieldDefinition $definition): array
    {
        return [
            'type' => $this->key(),
            'config' => $definition->config ?? [],
        ];
    }
}
