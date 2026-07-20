<?php

namespace App\Http\Requests\OpportunityStatuses;

use App\DataObjects\OpportunityStatuses\UpdateOpportunityStatusData;
use App\Enums\StatusGroup;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\OpportunityStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/opportunity-statuses/{opportunityStatus}
 * (spec 0043). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $opportunityStatus)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific model. `name` is unique ignoring self (BR-3). `sort_order`
 * is not accepted here (see App\Services\Statuses\StatusOrderManager);
 * `group` (App\Enums\StatusGroup) — App\Services\Statuses\SystemStatusGuard
 * rejects it outright, at the Service layer, when the target row is a
 * system status.
 */
class UpdateOpportunityStatusRequest extends FormRequest
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
        /** @var OpportunityStatus $opportunityStatus */
        $opportunityStatus = $this->route('opportunityStatus');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('opportunity_statuses', 'name')->ignore($opportunityStatus->id)],
            'color' => ['sometimes', 'nullable', 'string', 'max:32'],
            'group' => ['sometimes', 'string', Rule::enum(StatusGroup::class)],
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
        /** @var OpportunityStatus $opportunityStatus */
        $opportunityStatus = $this->route('opportunityStatus');

        return $opportunityStatus;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateOpportunityStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateOpportunityStatusData::fromValidated($validated);
    }
}
