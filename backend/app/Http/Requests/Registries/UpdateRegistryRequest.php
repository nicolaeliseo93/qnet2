<?php

namespace App\Http\Requests\Registries;

use App\DataObjects\Registries\UpdateRegistryData;
use App\Enums\AgreementStatusEnum;
use App\Enums\SizeClassEnum;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use App\Models\Registry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/registries/{registry} (spec 0020).
 * Every field is `sometimes` to support partial PATCH updates:
 * `source_id:null` clears the source; `personal_data` is optional (absent
 * leaves the card untouched, mirrors UpdateReferentRequest).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $registry)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $registry.
 */
class UpdateRegistryRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesUserProfile;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the RegistryPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'source_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sources', 'id')],
            'sector_ids' => ['sometimes', 'array'],
            'sector_ids.*' => ['integer', Rule::exists('sectors', 'id')],
            'referent_ids' => ['sometimes', 'array'],
            'referent_ids.*' => ['integer', Rule::exists('referents', 'id')],
            // MAX 4 internal managers is a validation-layer rule (spec 0020
            // scope note), never a DB constraint.
            'manager_ids' => ['sometimes', 'array', 'max:4'],
            'manager_ids.*' => ['integer', Rule::exists('users', 'id')],
            // Supervisor is an INTERNAL user (like managers); commercial/reporter
            // stay external referents.
            'supervisor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'commercial_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'vat_group' => ['sometimes', 'nullable', 'string', 'max:191'],
            'is_supplier' => ['sometimes', 'boolean'],
            'is_qualified_supplier' => ['sometimes', 'boolean'],
            'agreement_status' => ['sometimes', 'nullable', Rule::enum(AgreementStatusEnum::class)],
            'agreement_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'size_class' => ['sometimes', 'nullable', Rule::enum(SizeClassEnum::class)],
            'employee_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ], $this->profileRules());
    }

    /**
     * Apply the per-type contact `value` rules for the nested profile and
     * the field-level authorization gate (spec 0004).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateProfile($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'registries';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Registry $registry */
        $registry = $this->route('registry');

        return $registry;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects). The
     * nested profile is read separately via toProfile() (ValidatesUserProfile).
     */
    public function toData(): UpdateRegistryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateRegistryData::fromValidated($validated);
    }
}
