<?php

namespace App\Http\Requests\Attributes;

use App\DataObjects\Attributes\UpdateAttributeData;
use App\Enums\AttributeType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Attribute;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/attributes/{attribute} (spec
 * 0017). Every field is `sometimes` to support partial PATCH updates:
 * `options`, when submitted, is a full-replace of the option list; the
 * data_type-immutability guard (attribute already has product values) is
 * enforced by AttributeService, not here (it needs the model's current
 * values). Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $attribute)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific model.
 */
class UpdateAttributeRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via AttributePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Attribute $attribute */
        $attribute = $this->route('attribute');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('attributes', 'code')->ignore($attribute->id)],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'data_type' => ['sometimes', Rule::enum(AttributeType::class)],
            'options' => ['sometimes', 'array'],
            'options.*.value' => ['required', 'string', 'max:191'],
            'options.*.label' => ['required', 'string', 'max:191'],
            'options.*.sort_order' => ['sometimes', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateOptionValuesUnique($validator);
        });
    }

    /**
     * A submitted `options` full-replace must never carry duplicate values
     * (the ENUM-requires-at-least-one-option guard, including when data_type
     * is CHANGING to ENUM, is enforced by AttributeService — it needs the
     * model's current option count when `options` is not submitted).
     */
    private function validateOptionValuesUnique(Validator $validator): void
    {
        $options = $this->input('options');

        if (! is_array($options) || $options === []) {
            return;
        }

        $values = array_column($options, 'value');

        if (count($values) !== count(array_unique($values))) {
            $validator->errors()->add('options', 'Option values must be unique.');
        }
    }

    protected function authorizationResource(): string
    {
        return 'attributes';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Attribute $attribute */
        $attribute = $this->route('attribute');

        return $attribute;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateAttributeData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateAttributeData::fromValidated($validated);
    }
}
