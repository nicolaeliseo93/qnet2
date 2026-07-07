<?php

namespace App\Http\Requests\Attributes;

use App\DataObjects\Attributes\CreateAttributeData;
use App\Enums\AttributeType;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/attributes (spec 0017).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', Attribute::class)). `options` is required and
 * non-empty ONLY when data_type is ENUM (AC-003); it is silently ignored by
 * the Service for every other data type. EnforcesFieldPermissions (spec 0004)
 * additionally rejects any submitted field the actor cannot edit (create
 * context, model = null).
 */
class StoreAttributeRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('attributes', 'code')],
            'name' => ['required', 'string', 'max:191'],
            'data_type' => ['required', Rule::enum(AttributeType::class)],
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
            $this->validateEnumOptions($validator);
        });
    }

    /**
     * ENUM requires at least one option, each with a unique `value`; every
     * other data type ignores a submitted `options` key entirely (AC-003).
     */
    private function validateEnumOptions(Validator $validator): void
    {
        if ($this->input('data_type') !== AttributeType::Enum->value) {
            return;
        }

        $options = $this->input('options', []);

        if (! is_array($options) || $options === []) {
            $validator->errors()->add('options', 'At least one option is required for an ENUM attribute.');

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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateAttributeData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateAttributeData::fromValidated($validated);
    }
}
