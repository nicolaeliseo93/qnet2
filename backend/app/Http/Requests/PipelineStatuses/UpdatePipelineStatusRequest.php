<?php

namespace App\Http\Requests\PipelineStatuses;

use App\DataObjects\PipelineStatuses\UpdatePipelineStatusData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\PipelineStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PUT/PATCH /api/pipeline-statuses/{pipelineStatus}
 * (spec 0023). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $pipelineStatus)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit on
 * this specific model.
 */
class UpdatePipelineStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via PipelineStatusPolicy.
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
        return 'pipeline-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var PipelineStatus $pipelineStatus */
        $pipelineStatus = $this->route('pipelineStatus');

        return $pipelineStatus;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdatePipelineStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdatePipelineStatusData::fromValidated($validated);
    }
}
