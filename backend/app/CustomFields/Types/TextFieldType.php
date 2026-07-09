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
 * Single-line free text (spec 0021 MVP). `config`: minLength, maxLength,
 * regex (a full PCRE pattern with delimiters, e.g. "/^[A-Z]+$/"), transform
 * (uppercase|lowercase|capitalize — applied on write only, never on read).
 */
class TextFieldType implements FieldTypeHandler
{
    use AppliesTextFilter;
    use DerivesRequiredRule;
    use OrdersByJsonPath;
    use ResolvesDistinctJsonValues;
    use ResolvesJsonColumn;

    public function key(): string
    {
        return 'text';
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

        if (isset($config['minLength'])) {
            $rules[] = "min:{$config['minLength']}";
        }

        if (isset($config['maxLength'])) {
            $rules[] = "max:{$config['maxLength']}";
        }

        if (! empty($config['regex'])) {
            $rules[] = "regex:{$config['regex']}";
        }

        return $rules;
    }

    public function normalizeForStore(mixed $value, CustomFieldDefinition $definition): mixed
    {
        if ($value === null) {
            return null;
        }

        return $this->applyTransform((string) $value, $definition->config ?? []);
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

    /**
     * @param  array<string, mixed>  $config
     */
    private function applyTransform(string $value, array $config): string
    {
        return match ($config['transform'] ?? null) {
            'uppercase' => mb_strtoupper($value),
            'lowercase' => mb_strtolower($value),
            'capitalize' => mb_convert_case($value, MB_CASE_TITLE),
            default => $value,
        };
    }
}
