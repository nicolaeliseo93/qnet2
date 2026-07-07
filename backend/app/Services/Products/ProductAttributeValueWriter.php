<?php

namespace App\Services\Products;

use App\Enums\AttributeType;
use App\Models\AttributeOption;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Collection;

/**
 * EAV validation + typed-column routing for a product's dynamic attribute
 * values (spec 0017), extracted out of ProductService (file-size split,
 * engineering.md §6): given the category's EFFECTIVE attribute catalogue
 * (ProductCategoryService::effectiveAttributes shape — id/code/name/
 * data_type/is_required/options), validates a submitted `[{attribute_id,
 * value}]` payload and upserts each value into the value_* column matching
 * its attribute's data_type (or `option_id` for ENUM).
 */
final class ProductAttributeValueWriter
{
    /**
     * Every submitted attribute_id must belong to the category's effective
     * attributes; its value must be coherent with the attribute's data_type;
     * every attribute flagged is_required in $effective must be present in
     * $submitted with a non-empty value (AC-015).
     *
     * @param  Collection<int, array<string, mixed>>  $effective
     * @param  array<int, array{attribute_id: int, value: mixed}>  $submitted
     */
    public function guardValues(Collection $effective, array $submitted): void
    {
        $effectiveById = $effective->keyBy('id');
        $submittedByAttributeId = collect($submitted)->keyBy(static fn (array $row): int => (int) $row['attribute_id']);

        foreach ($submittedByAttributeId as $attributeId => $row) {
            $definition = $effectiveById->get($attributeId);

            if ($definition === null) {
                abort(422, "Attribute {$attributeId} is not assigned to this product's category.");
            }

            $this->assertValueMatchesType($definition, $row['value'] ?? null);
        }

        foreach ($effective as $definition) {
            if (! $definition['is_required']) {
                continue;
            }

            $row = $submittedByAttributeId->get($definition['id']);

            if ($row === null || $this->isEmpty($row['value'] ?? null)) {
                abort(422, "Attribute \"{$definition['code']}\" is required.");
            }
        }
    }

    /**
     * Full-replace of $product's dynamic values: upsert every submitted row
     * into its typed column, then drop any value not in this submission.
     *
     * @param  Collection<int, array<string, mixed>>  $effective
     * @param  array<int, array{attribute_id: int, value: mixed}>  $submitted
     */
    public function replaceValues(Product $product, Collection $effective, array $submitted): void
    {
        $effectiveById = $effective->keyBy('id');
        $submittedIds = [];

        foreach ($submitted as $row) {
            $attributeId = (int) $row['attribute_id'];
            $submittedIds[] = $attributeId;

            $this->upsertValue($product, $effectiveById->get($attributeId), $row['value'] ?? null);
        }

        $product->attributeValues()->whereNotIn('attribute_id', $submittedIds)->delete();
    }

    /**
     * Drop values for attributes no longer in $effective (a category change
     * with no explicit `attributes` payload — AC-017).
     *
     * @param  Collection<int, array<string, mixed>>  $effective
     */
    public function pruneIrrelevantValues(Product $product, Collection $effective): void
    {
        $effectiveIds = $effective->pluck('id')->all();

        $product->attributeValues()->whereNotIn('attribute_id', $effectiveIds)->delete();
    }

    /**
     * $product's CURRENT values (restricted to attribute ids still in
     * $effective), projected into the `[{attribute_id, value}]` submission
     * shape, so a category change with no `attributes` payload can still run
     * through guardValues() for the new required set.
     *
     * @param  Collection<int, array<string, mixed>>  $effective
     * @return array<int, array{attribute_id: int, value: mixed}>
     */
    public function currentValuesAsSubmission(Product $product, Collection $effective): array
    {
        $effectiveIds = $effective->pluck('id')->all();

        return $product->attributeValues()
            ->with(['attribute', 'option'])
            ->whereIn('attribute_id', $effectiveIds)
            ->get()
            ->map(static fn (ProductAttributeValue $value): array => [
                'attribute_id' => $value->attribute_id,
                'value' => $value->value,
            ])
            ->all();
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertValueMatchesType(array $definition, mixed $value): void
    {
        if ($this->isEmpty($value)) {
            // Emptiness is only invalid for a REQUIRED attribute, already
            // checked separately in guardValues().
            return;
        }

        match ($definition['data_type']) {
            AttributeType::Integer => $this->assertInteger($definition, $value),
            AttributeType::Decimal => $this->assertNumeric($definition, $value),
            AttributeType::Boolean => $this->assertBoolean($definition, $value),
            AttributeType::Enum => $this->assertEnumOption($definition, $value),
            default => $this->assertString($definition, $value),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertInteger(array $definition, mixed $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            abort(422, "Attribute \"{$definition['code']}\" must be an integer.");
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertNumeric(array $definition, mixed $value): void
    {
        if (! is_numeric($value)) {
            abort(422, "Attribute \"{$definition['code']}\" must be a number.");
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertBoolean(array $definition, mixed $value): void
    {
        if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            abort(422, "Attribute \"{$definition['code']}\" must be a boolean.");
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertEnumOption(array $definition, mixed $value): void
    {
        $allowed = collect($definition['options'])->pluck('value')->all();

        if (! in_array((string) $value, $allowed, true)) {
            abort(422, "Attribute \"{$definition['code']}\" must be one of the allowed options.");
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertString(array $definition, mixed $value): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            abort(422, "Attribute \"{$definition['code']}\" must be a string.");
        }
    }

    /**
     * Upsert one value into the column matching $definition's data_type (or
     * `option_id` for ENUM) — the write-side counterpart of
     * ProductAttributeValue::getValueAttribute()'s read-side routing. An
     * empty value removes the row entirely (a non-required attribute simply
     * left unset).
     *
     * @param  array<string, mixed>|null  $definition
     */
    private function upsertValue(Product $product, ?array $definition, mixed $value): void
    {
        if ($definition === null) {
            // Guarded against in guardValues(); defensive no-op here.
            return;
        }

        if ($this->isEmpty($value)) {
            $product->attributeValues()->where('attribute_id', $definition['id'])->delete();

            return;
        }

        $columns = [
            'value_string' => null,
            'value_integer' => null,
            'value_decimal' => null,
            'value_boolean' => null,
            'option_id' => null,
        ];

        match ($definition['data_type']) {
            AttributeType::Integer => $columns['value_integer'] = (int) $value,
            AttributeType::Decimal => $columns['value_decimal'] = (float) $value,
            AttributeType::Boolean => $columns['value_boolean'] = filter_var($value, FILTER_VALIDATE_BOOLEAN),
            AttributeType::Enum => $columns['option_id'] = $this->resolveOptionId($definition, $value),
            default => $columns['value_string'] = (string) $value,
        };

        $product->attributeValues()->updateOrCreate(['attribute_id' => $definition['id']], $columns);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function resolveOptionId(array $definition, mixed $value): ?int
    {
        return AttributeOption::query()
            ->where('attribute_id', $definition['id'])
            ->where('value', (string) $value)
            ->value('id');
    }
}
