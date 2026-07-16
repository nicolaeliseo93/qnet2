<?php

namespace App\Http\Requests\StatusGroups;

use App\DataObjects\StatusGroups\UpdateStatusGroupData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\StatusGroup;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/status-groups/{statusGroup} (spec
 * 0039). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $statusGroup)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model. `name` is unique ignoring self, mirroring
 * UpdateLeadStatusRequest.
 */
class UpdateStatusGroupRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via StatusGroupPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var StatusGroup $statusGroup */
        $statusGroup = $this->route('statusGroup');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('status_groups', 'name')->ignore($statusGroup->id)],
            'color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort_order' => ['sometimes', 'integer'],
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
        return 'status-groups';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var StatusGroup $statusGroup */
        $statusGroup = $this->route('statusGroup');

        return $statusGroup;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateStatusGroupData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateStatusGroupData::fromValidated($validated);
    }
}
