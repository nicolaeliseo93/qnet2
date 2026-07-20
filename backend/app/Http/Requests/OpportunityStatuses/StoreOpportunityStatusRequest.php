<?php

namespace App\Http\Requests\OpportunityStatuses;

use App\DataObjects\OpportunityStatuses\CreateOpportunityStatusData;
use App\Enums\StatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/opportunity-statuses (spec 0043).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', OpportunityStatus::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null). `name` is unique (BR-3). `sort_order` is
 * not accepted here (absent from rules() -> validated() silently drops it,
 * "unknown field ignorato") — server-managed, see
 * App\Services\Statuses\StatusOrderManager. `group` (App\Enums\StatusGroup)
 * is REQUIRED — every row carries a classification.
 */
class StoreOpportunityStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via OpportunityStatusPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', Rule::unique('opportunity_statuses', 'name')],
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
        return 'opportunity-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateOpportunityStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateOpportunityStatusData::fromValidated($validated);
    }
}
