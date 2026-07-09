<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

use App\Models\CustomFieldDefinition;

/**
 * Shared scaffolding for the string-backed scalar field types added on top of
 * the spec 0021 MVP (date, datetime, time, email, url, color). They all store
 * a plain string, render as a `text` grid column with a `text` filter, and
 * order/distinct/filter through the same JSON-path helpers as TextFieldType —
 * differing ONLY in their `key()` and `validationRules()` (the input format
 * they accept). Composing those five concerns here keeps each handler to just
 * those two type-specific methods (OCP), without touching the MVP handlers.
 */
trait HandlesScalarStringField
{
    use AppliesTextFilter;
    use DerivesRequiredRule;
    use OrdersByJsonPath;
    use ResolvesDistinctJsonValues;
    use ResolvesJsonColumn;

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

    public function normalizeForStore(mixed $value, CustomFieldDefinition $definition): mixed
    {
        return $value === null ? null : (string) $value;
    }

    public function resolveForRead(mixed $stored, CustomFieldDefinition $definition): mixed
    {
        return $stored;
    }

    /**
     * @return array<string, mixed>
     */
    public function toMeta(CustomFieldDefinition $definition): array
    {
        return [
            'type' => $this->key(),
            'config' => $definition->config ?? [],
        ];
    }
}
