<?php

declare(strict_types=1);

namespace App\Http\Requests\OpportunityWorkflows;

use App\DataObjects\OpportunityWorkflows\CreateOpportunityWorkflowData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\OpportunityWorkflows\Concerns\ValidatesWorkflowCriteria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/opportunity-workflows (spec 0047 Lane
 * A). Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', OpportunityWorkflow::class)).
 * `criteria` is required (min:1, AC-008); `statuses` is optional and covers
 * ONLY the intermediate custom rows — the 2 system rows (open/closed) are
 * created automatically by the Service.
 */
class StoreOpportunityWorkflowRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesWorkflowCriteria;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', Rule::unique('opportunity_workflows', 'name')],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->criteriaRules(required: true),
            ...$this->statusesRules(allowIds: false),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateCriteria($validator, excludeWorkflowId: null);
        });
    }

    protected function authorizationResource(): string
    {
        return 'opportunity-workflows';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateOpportunityWorkflowData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateOpportunityWorkflowData::fromValidated($validated);
    }
}
