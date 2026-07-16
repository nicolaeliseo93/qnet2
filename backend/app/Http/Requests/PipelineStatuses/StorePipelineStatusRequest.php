<?php

namespace App\Http\Requests\PipelineStatuses;

use App\DataObjects\PipelineStatuses\CreatePipelineStatusData;
use App\Enums\StatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/pipeline-statuses (spec 0023).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', PipelineStatus::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null). spec 0039, D-5: `sort_order` is no longer
 * accepted here (absent from rules() -> validated() silently drops it,
 * "unknown field ignorato") — server-managed, see
 * App\Services\Statuses\StatusOrderManager. `group` (pivot,
 * App\Enums\StatusGroup) is REQUIRED — every row carries a classification.
 */
class StorePipelineStatusRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:191'],
            'color' => ['nullable', 'string', 'max:32'],
            'group' => ['required', 'string', Rule::enum(StatusGroup::class)],
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreatePipelineStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreatePipelineStatusData::fromValidated($validated);
    }
}
