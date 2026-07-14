<?php

namespace App\Http\Requests\Leads;

use App\DataObjects\Leads\UpdateLeadData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/leads/{lead} (spec 0024). Every
 * field is `sometimes` (partial PATCH); `referent_id`/`campaign_id`, IF
 * submitted, cannot be null (BR-1 — mandatory fields never accept an empty
 * value once touched).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $lead)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $lead.
 */
class UpdateLeadRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via LeadPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'referent_id' => ['sometimes', 'required', 'integer', Rule::exists('referents', 'id')],
            'campaign_id' => ['sometimes', 'required', 'integer', Rule::exists('campaigns', 'id')],
            'operational_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'source_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sources', 'id')],
            'operator_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_converted' => ['sometimes', 'boolean'],
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
        return 'leads';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Model $lead */
        $lead = $this->route('lead');

        return $lead;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateLeadData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateLeadData::fromValidated($validated);
    }
}
