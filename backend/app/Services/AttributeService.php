<?php

namespace App\Services;

use App\DataObjects\Attributes\CreateAttributeData;
use App\DataObjects\Attributes\UpdateAttributeData;
use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `attributes` resource (spec 0017): create/update
 * (including the ENUM-options full-replace and the data_type-immutability
 * guard) and a restrictive delete. The controller stays thin; this Service
 * is the single authority.
 */
class AttributeService
{
    public function create(CreateAttributeData $data): Attribute
    {
        return DB::transaction(function () use ($data): Attribute {
            $dataType = AttributeType::from($data->dataType);
            $this->guardEnumHasOptions($dataType, count($data->options ?? []));

            /** @var Attribute $attribute */
            $attribute = Attribute::create([
                'code' => $data->code,
                'name' => $data->name,
                'data_type' => $dataType,
            ]);

            if ($dataType === AttributeType::Enum && $data->hasOptions()) {
                $this->replaceOptions($attribute, $data->options);
            }

            return $attribute->fresh('options');
        });
    }

    public function update(Attribute $attribute, UpdateAttributeData $data): Attribute
    {
        $this->guardDataTypeImmutable($attribute, $data);

        return DB::transaction(function () use ($attribute, $data): Attribute {
            $finalDataType = $data->hasDataType() ? AttributeType::from($data->dataType) : $attribute->data_type;

            $attributes = $data->submittedAttributes();

            if ($data->hasDataType()) {
                $attributes['data_type'] = $finalDataType;
            }

            if ($attributes !== []) {
                $attribute->update($attributes);
            }

            if ($data->hasOptions()) {
                $this->guardEnumHasOptions($finalDataType, count($data->options));
                $this->replaceOptions($attribute, $data->options);
            } else {
                // options untouched: the ENUM-needs-at-least-one-option
                // invariant must still hold against the PERSISTED count
                // (relevant when data_type is changing TO enum without a
                // fresh options payload).
                $this->guardEnumHasOptions($finalDataType, $attribute->options()->count());
            }

            return $attribute->fresh('options');
        });
    }

    /**
     * Restrictive delete: an attribute assigned to a category, or already
     * carrying recorded product values, cannot be removed (it would silently
     * orphan the category's form / a product's data).
     */
    public function delete(Attribute $attribute): void
    {
        if ($attribute->categories()->exists() || $attribute->values()->exists()) {
            abort(409, 'This attribute is assigned to a category or has recorded product values and cannot be deleted.');
        }

        $attribute->delete();
    }

    /**
     * An ENUM attribute must always carry at least one option.
     */
    private function guardEnumHasOptions(AttributeType $dataType, int $optionsCount): void
    {
        if ($dataType === AttributeType::Enum && $optionsCount === 0) {
            abort(422, 'At least one option is required for an ENUM attribute.');
        }
    }

    /**
     * data_type is immutable once the attribute has recorded product values
     * (changing it would leave those typed value_* columns meaningless).
     */
    private function guardDataTypeImmutable(Attribute $attribute, UpdateAttributeData $data): void
    {
        if (! $data->hasDataType() || $data->dataType === $attribute->data_type->value) {
            return;
        }

        if ($attribute->values()->exists()) {
            abort(422, 'The data type cannot be changed once the attribute has recorded product values.');
        }
    }

    /**
     * Full-replace of the attribute's option list: delete then recreate
     * inside the caller's transaction, so a failure never half-applies.
     * Options removed from the submission are gone; any product value that
     * referenced them falls back via `option_id` nullOnDelete (migration).
     *
     * @param  array<int, array{value: string, label: string, sort_order?: int}>  $options
     */
    private function replaceOptions(Attribute $attribute, array $options): void
    {
        $attribute->options()->delete();

        $rows = [];

        foreach ($options as $index => $option) {
            $rows[] = [
                'value' => (string) $option['value'],
                'label' => (string) $option['label'],
                'sort_order' => (int) ($option['sort_order'] ?? $index),
            ];
        }

        $attribute->options()->createMany($rows);
    }
}
