<?php

namespace App\Http\Requests\Referents;

use App\DataObjects\Referents\CreateReferentData;
use App\Enums\ReferentContactScopeEnum;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/referents (spec 0016).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Referent::class)). Reuses
 * ValidatesUserProfile verbatim for the nested `personal_data` object
 * (required on create — it is the only source of the derived `referents.name`,
 * mirroring StoreUserRequest/ADR 0012). EnforcesFieldPermissions (spec 0004)
 * additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreReferentRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesUserProfile;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the ReferentPolicy.
        return true;
    }

    /**
     * `personal_data` is mandatory on create: it is the only source of the
     * derived `referents.name` (mirrors StoreUserRequest, ADR 0012).
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
            'referent_type_id' => ['nullable', 'integer', Rule::exists('referent_types', 'id')],
            'contact_scope' => ['required', Rule::enum(ReferentContactScopeEnum::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
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
        return 'referents';
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
    public function toData(): CreateReferentData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateReferentData::fromValidated($validated);
    }
}
