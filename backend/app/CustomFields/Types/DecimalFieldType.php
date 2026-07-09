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
use Closure;

/**
 * Decimal/float field (spec 0021 MVP). `config`: min, max, step, decimals
 * (max number of decimal places honoured on write and enforced on validate).
 */
class DecimalFieldType implements FieldTypeHandler
{
    use AppliesNumberFilter;
    use DerivesRequiredRule;
    use OrdersByJsonPath;
    use ResolvesDistinctJsonValues;
    use ResolvesJsonColumn;
    use ValidatesNumericStep;

    public function key(): string
    {
        return 'decimal';
    }

    public function storageType(): string
    {
        return 'decimal';
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
        $rules = [$this->requiredOrNullable($definition), 'numeric'];

        if (isset($config['min'])) {
            $rules[] = "min:{$config['min']}";
        }

        if (isset($config['max'])) {
            $rules[] = "max:{$config['max']}";
        }

        if (isset($config['decimals'])) {
            $rules[] = $this->decimalsRule((int) $config['decimals']);
        }

        if (isset($config['step']) && (float) $config['step'] > 0) {
            $rules[] = $this->stepRule((float) $config['step'], (float) ($config['min'] ?? 0));
        }

        return $rules;
    }

    public function normalizeForStore(mixed $value, CustomFieldDefinition $definition): mixed
    {
        if ($value === null) {
            return null;
        }

        $config = $definition->config ?? [];
        $float = (float) $value;

        return isset($config['decimals']) ? round($float, (int) $config['decimals']) : $float;
    }

    public function resolveForRead(mixed $stored, CustomFieldDefinition $definition): mixed
    {
        return $stored === null ? null : (float) $stored;
    }

    public function toMeta(CustomFieldDefinition $definition): array
    {
        return [
            'type' => $this->key(),
            'config' => $definition->config ?? [],
        ];
    }

    private function decimalsRule(int $decimals): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($decimals): void {
            if (! is_numeric($value)) {
                return;
            }

            $rounded = round((float) $value, $decimals);

            if (abs($rounded - (float) $value) > 1e-9) {
                $fail("The :attribute may have at most {$decimals} decimal place(s).");
            }
        };
    }
}
