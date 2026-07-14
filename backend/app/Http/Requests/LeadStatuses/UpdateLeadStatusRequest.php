<?php

namespace App\Http\Requests\LeadStatuses;

use App\DataObjects\LeadStatuses\UpdateLeadStatusData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\LeadStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/lead-statuses/{leadStatus} (spec
 * 0029). Every field is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $leadStatus)). EnforcesFieldPermissions (spec 0004)
 * additionally rejects any submitted field the actor cannot edit on this
 * specific model. `name` is unique ignoring self (BR-2/D-4), unlike
 * UpdateProjectStatusRequest.
 */
class UpdateLeadStatusRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via LeadStatusPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var LeadStatus $leadStatus */
        $leadStatus = $this->route('leadStatus');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('lead_statuses', 'name')->ignore($leadStatus->id)],
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
        return 'lead-statuses';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var LeadStatus $leadStatus */
        $leadStatus = $this->route('leadStatus');

        return $leadStatus;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateLeadStatusData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateLeadStatusData::fromValidated($validated);
    }
}
