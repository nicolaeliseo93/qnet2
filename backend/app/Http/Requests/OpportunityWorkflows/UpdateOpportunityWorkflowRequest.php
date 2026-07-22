<?php

declare(strict_types=1);

namespace App\Http\Requests\OpportunityWorkflows;

use App\DataObjects\OpportunityWorkflows\UpdateOpportunityWorkflowData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\OpportunityWorkflows\Concerns\ValidatesWorkflowCriteria;
use App\Models\OpportunityWorkflow;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/opportunity-workflows/{opportunityWorkflow}
 * (spec 0047 Lane A). Every field is `sometimes` (partial PATCH).
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $opportunityWorkflow)). `criteria`,
 * when submitted, is an authoritative full-replace sync (min:1 — a workflow
 * can never be left with zero criteria); `statuses`, when submitted, syncs
 * only the CUSTOM rows (`statuses.*.id` optional: present = update, absent =
 * new) — the system rows accept every descriptive field but never a `group`
 * change, enforced at the
 * Service/WorkflowStatusWriter layer.
 */
class UpdateOpportunityWorkflowRequest extends FormRequest
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
        /** @var OpportunityWorkflow $opportunityWorkflow */
        $opportunityWorkflow = $this->route('opportunityWorkflow');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('opportunity_workflows', 'name')->ignore($opportunityWorkflow->id)],
            'is_active' => ['sometimes', 'boolean'],
            ...$this->criteriaRules(required: false),
            ...$this->statusesRules(allowIds: true),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);

            /** @var OpportunityWorkflow $opportunityWorkflow */
            $opportunityWorkflow = $this->route('opportunityWorkflow');

            $this->validateCriteria($validator, excludeWorkflowId: $opportunityWorkflow->id);
        });
    }

    protected function authorizationResource(): string
    {
        return 'opportunity-workflows';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var OpportunityWorkflow $opportunityWorkflow */
        $opportunityWorkflow = $this->route('opportunityWorkflow');

        return $opportunityWorkflow;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateOpportunityWorkflowData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateOpportunityWorkflowData::fromValidated($validated);
    }
}
