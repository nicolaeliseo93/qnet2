<?php

namespace App\Http\Requests\Registries;

use App\DataObjects\Registries\CreateRegistryData;
use App\Enums\AgreementStatusEnum;
use App\Enums\SizeClassEnum;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/registries (spec 0020).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Registry::class)). Reuses
 * ValidatesUserProfile verbatim for the nested `personal_data` object
 * (required on create — it is the only source of the derived
 * `registries.name`, mirroring StoreReferentRequest). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreRegistryRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesUserProfile;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the RegistryPolicy.
        return true;
    }

    /**
     * `personal_data` is mandatory on create: it is the only source of the
     * derived `registries.name` (mirrors StoreReferentRequest).
     */
    protected function profileRequired(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'source_id' => ['nullable', 'integer', Rule::exists('sources', 'id')],
            'ea_sector_ids' => ['sometimes', 'array'],
            'ea_sector_ids.*' => ['integer', Rule::exists('ea_sectors', 'id')],
            'referent_ids' => ['sometimes', 'array'],
            'referent_ids.*' => ['integer', Rule::exists('referents', 'id')],
            // MAX 4 internal managers is a validation-layer rule (spec 0020
            // scope note), never a DB constraint.
            'manager_ids' => ['sometimes', 'array', 'max:4'],
            'manager_ids.*' => ['integer', Rule::exists('users', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'commercial_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'vat_group' => ['nullable', 'string', 'max:191'],
            'is_supplier' => ['required', 'boolean'],
            'is_qualified_supplier' => ['sometimes', 'boolean'],
            'agreement_status' => ['nullable', Rule::enum(AgreementStatusEnum::class)],
            'agreement_notes' => ['nullable', 'string', 'max:5000'],
            'size_class' => ['nullable', Rule::enum(SizeClassEnum::class)],
            'employee_count' => ['nullable', 'integer', 'min:0'],
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects). The
     * nested profile is read separately via toProfile() (ValidatesUserProfile).
     */
    public function toData(): CreateRegistryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateRegistryData::fromValidated($validated);
    }
}
