<?php

declare(strict_types=1);

namespace App\CustomFields\Types\Concerns;

/**
 * Builds the bound JSON-path column expression for a custom field's storage
 * column (`custom_field_values.values`). `$jsonKey` MUST always be the
 * allow-listed definition key resolved by the caller, never raw request
 * input (backend.md §8 / security.md §8).
 */
trait ResolvesJsonColumn
{
    private function jsonColumn(string $jsonKey): string
    {
        return "custom_field_values.values->{$jsonKey}";
    }
}
