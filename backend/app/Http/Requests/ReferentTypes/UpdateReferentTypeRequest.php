<?php

namespace App\Http\Requests\ReferentTypes;

use App\DataObjects\ReferentTypes\UpdateReferentTypeData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\ReferentType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PUT/PATCH /api/referent-types/{referentType}
 * (spec 0016). `name` is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $referentType)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model.
 */
class UpdateReferentTypeRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ReferentTypePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'referent-types';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var ReferentType $referentType */
        $referentType = $this->route('referentType');

        return $referentType;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateReferentTypeData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateReferentTypeData::fromValidated($validated);
    }
}
