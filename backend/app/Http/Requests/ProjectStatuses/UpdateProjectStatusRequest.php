<?php

namespace App\Http\Requests\ProjectStatuses;

use App\DataObjects\ProjectStatuses\UpdateProjectStatusData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\ProjectStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PUT/PATCH /api/project-statuses/{projectStatus}
 * (spec 0023). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $projectStatus)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model.
 */
class UpdateProjectStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ProjectStatusPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
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
        return 'project-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var ProjectStatus $projectStatus */
        $projectStatus = $this->route('projectStatus');

        return $projectStatus;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateProjectStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateProjectStatusData::fromValidated($validated);
    }
}
