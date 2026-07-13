<?php

namespace App\Http\Requests\Projects;

use App\DataObjects\Projects\UpdateProjectData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Project;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/projects/{project} (spec 0023).
 * Every field is `sometimes` to support partial PATCH updates; `code` is
 * intentionally NOT a rule: it is server-generated and read-only (BR-1), so
 * any submitted value is silently dropped by validated().
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $project)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $project.
 */
class UpdateProjectRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via ProjectPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'project_status_id' => ['sometimes', 'required', 'integer', Rule::exists('project_statuses', 'id')],
            'description' => ['sometimes', 'nullable', 'string'],
            'registry_id' => ['sometimes', 'nullable', 'integer', Rule::exists('registries', 'id')],
            'source_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sources', 'id')],
            'business_function_id' => ['sometimes', 'nullable', 'integer', Rule::exists('business_functions', 'id')],
            'state_id' => ['sometimes', 'nullable', 'integer', Rule::exists('states', 'id')],
            'product_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id')],
            'partner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'total_budget' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'target_lead' => ['sometimes', 'nullable', 'integer', 'min:0'],
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
        return 'projects';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $project;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateProjectData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateProjectData::fromValidated($validated);
    }
}
