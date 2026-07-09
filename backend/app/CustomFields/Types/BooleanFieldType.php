<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Types\Concerns\AppliesBooleanFilter;
use App\CustomFields\Types\Concerns\DerivesRequiredRule;
use App\CustomFields\Types\Concerns\OrdersByJsonPath;
use App\CustomFields\Types\Concerns\ResolvesDistinctJsonValues;
use App\CustomFields\Types\Concerns\ResolvesJsonColumn;
use App\Models\CustomFieldDefinition;

/**
 * Boolean field (spec 0021 MVP). `config.display`: checkbox|switch — a
 * frontend rendering hint only, passed through toMeta() untouched.
 */
class BooleanFieldType implements FieldTypeHandler
{
    use AppliesBooleanFilter;
    use DerivesRequiredRule;
    use OrdersByJsonPath;
    use ResolvesDistinctJsonValues;
    use ResolvesJsonColumn;

    public function key(): string
    {
        return 'boolean';
    }

    public function storageType(): string
    {
        return 'boolean';
    }

    public function columnType(): string
    {
        return 'boolean';
    }

    public function filterType(): string
    {
        return 'boolean';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        return [$this->requiredOrNullable($definition), 'boolean'];
    }

    public function normalizeForStore(mixed $value, CustomFieldDefinition $definition): mixed
    {
        return $value === null ? null : (bool) $value;
    }

    public function resolveForRead(mixed $stored, CustomFieldDefinition $definition): mixed
    {
        return $stored === null ? null : (bool) $stored;
    }

    public function toMeta(CustomFieldDefinition $definition): array
    {
        return [
            'type' => $this->key(),
            'config' => $definition->config ?? [],
        ];
    }
}
