<?php

namespace App\Services;

use App\CustomFields\FieldTypeRegistry;
use App\DataObjects\Attributes\CreateAttributeData;
use App\DataObjects\Attributes\UpdateAttributeData;
use App\Models\Attribute;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `attributes` resource (spec 0017, aligned to the
 * custom fields' write pipeline — spec 0021): create/update (including the
 * ENUM-options full-replace) and a restrictive delete. The controller stays
 * thin; this Service is the single authority.
 */
class AttributeService
{
    public function __construct(private readonly FieldTypeRegistry $fieldTypeRegistry) {}

    public function create(CreateAttributeData $data): Attribute
    {
        return DB::transaction(function () use ($data): Attribute {
            $this->guardValidType($data->type);
            $this->guardEnumHasOptions($data->type, count($data->options ?? []));
            $this->guardRelationHasTarget($data->type, $data->relationTarget);

            /** @var Attribute $attribute */
            $attribute = Attribute::create([
                'code' => $data->code,
                'name' => $data->name,
                'type' => $data->type,
                'description' => $data->description,
                'help_text' => $data->helpText,
                'placeholder' => $data->placeholder,
                'icon' => $data->icon,
                'config' => $data->config,
                'relation_target' => $data->relationTarget,
            ]);

            if ($data->type === 'enum' && $data->hasOptions()) {
                $this->replaceOptions($attribute, $data->options);
            }

            return $attribute->fresh('options');
        });
    }

    public function update(Attribute $attribute, UpdateAttributeData $data): Attribute
    {
        return DB::transaction(function () use ($attribute, $data): Attribute {
            $finalType = $data->hasType() ? $data->type : $attribute->type;
            $finalRelationTarget = $data->relationTargetSubmitted ? $data->relationTarget : $attribute->relation_target;

            $this->guardValidType($finalType);

            $attributes = $data->submittedAttributes();

            if ($data->hasType()) {
                $attributes['type'] = $finalType;
            }

            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $attribute->fill($attributes)->save();

            if ($data->hasOptions()) {
                $this->guardEnumHasOptions($finalType, count($data->options));
                $this->replaceOptions($attribute, $data->options);
            } else {
                // options untouched: the ENUM-needs-at-least-one-option
                // invariant must still hold against the PERSISTED count
                // (relevant when type is changing TO enum without a fresh
                // options payload).
                $this->guardEnumHasOptions($finalType, $attribute->options()->count());
            }

            $this->guardRelationHasTarget($finalType, $finalRelationTarget);

            return $attribute->fresh('options');
        });
    }

    /**
     * Restrictive delete: an attribute assigned to a category cannot be
     * removed (it would silently orphan the category's form).
     */
    public function delete(Attribute $attribute): void
    {
        if ($attribute->categories()->exists()) {
            abort(409, 'This attribute is assigned to a category and cannot be deleted.');
        }

        $attribute->delete();
    }

    /**
     * `type` must be a registered FieldTypeRegistry key — the FormRequest
     * already enforces this via Rule::in(), but AttributesSource (spec 0013)
     * builds a CreateAttributeData directly from external data, bypassing
     * the FormRequest entirely, so the Service re-asserts it defensively.
     */
    private function guardValidType(string $type): void
    {
        if (! $this->fieldTypeRegistry->has($type)) {
            abort(422, "Unknown attribute type [{$type}].");
        }
    }

    /**
     * An ENUM attribute must always carry at least one option.
     */
    private function guardEnumHasOptions(string $type, int $optionsCount): void
    {
        if ($type === 'enum' && $optionsCount === 0) {
            abort(422, 'At least one option is required for an ENUM attribute.');
        }
    }

    /**
     * A RELATION attribute must always carry a relation_target.
     *
     * @param  array<string, mixed>|null  $relationTarget
     */
    private function guardRelationHasTarget(string $type, ?array $relationTarget): void
    {
        if ($type === 'relation' && $relationTarget === null) {
            abort(422, 'A relation_target is required for a RELATION attribute.');
        }
    }

    /**
     * Full-replace of the attribute's option list: delete then recreate
     * inside the caller's transaction, so a failure never half-applies.
     *
     * @param  array<int, array{value: string, label: string, color?: string|null, icon?: string|null, sort_order?: int, is_default?: bool}>  $options
     */
    private function replaceOptions(Attribute $attribute, array $options): void
    {
        $attribute->options()->delete();

        $rows = [];

        foreach ($options as $index => $option) {
            $rows[] = [
                'value' => (string) $option['value'],
                'label' => (string) $option['label'],
                'color' => $option['color'] ?? null,
                'icon' => $option['icon'] ?? null,
                'sort_order' => (int) ($option['sort_order'] ?? $index),
                'is_default' => (bool) ($option['is_default'] ?? false),
            ];
        }

        $attribute->options()->createMany($rows);
    }
}
