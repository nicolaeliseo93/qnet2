<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Types\Concerns\AppliesNumberFilter;
use App\CustomFields\Types\Concerns\DerivesRequiredRule;
use App\CustomFields\Types\Concerns\OrdersByJsonPath;
use App\CustomFields\Types\Concerns\ResolvesDistinctJsonValues;
use App\CustomFields\Types\Concerns\ResolvesJsonColumn;
use App\CustomFields\Types\Concerns\ValidatesNumericStep;
use App\Models\CustomFieldDefinition;

/**
 * Whole-number field (spec 0021 MVP). `config`: min, max, step.
 */
class IntegerFieldType implements FieldTypeHandler
{
    use AppliesNumberFilter;
    use DerivesRequiredRule;
    use OrdersByJsonPath;
    use ResolvesDistinctJsonValues;
    use ResolvesJsonColumn;
    use ValidatesNumericStep;

    public function key(): string
    {
        return 'integer';
    }

    public function storageType(): string
    {
        return 'integer';
    }

    public function columnType(): string
    {
        return 'number';
    }

    public function filterType(): string
    {
        return 'number';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        $config = $definition->config ?? [];
        $rules = [$this->requiredOrNullable($definition), 'integer'];

        if (isset($config['min'])) {
            $rules[] = "min:{$config['min']}";
        }

        if (isset($config['max'])) {
            $rules[] = "max:{$config['max']}";
        }

        if (isset($config['step']) && (float) $config['step'] > 0) {
            $rules[] = $this->stepRule((float) $config['step'], (float) ($config['min'] ?? 0));
        }

        return $rules;
    }

    public function normalizeForStore(mixed $value, CustomFieldDefinition $definition): mixed
    {
        return $value === null ? null : (int) round((float) $value);
    }

    public function resolveForRead(mixed $stored, CustomFieldDefinition $definition): mixed
    {
        return $stored === null ? null : (int) $stored;
    }

    public function toMeta(CustomFieldDefinition $definition): array
    {
        return [
            'type' => $this->key(),
            'config' => $definition->config ?? [],
        ];
    }
}
