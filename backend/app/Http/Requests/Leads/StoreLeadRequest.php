<?php

namespace App\Http\Requests\Leads;

use App\DataObjects\Leads\CreateLeadData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/leads (spec 0024, spec 0041 D-1).
 * `registry_id`/`campaign_id` are required (BR-1); the other 3 relations are optional,
 * `notes` is a plain nullable text. spec 0039, D-3: `lead_status_id` went
 * from `required` to `nullable` — an omitted/null FK falls back to the
 * system_key='new' status in LeadService::create() (server-side default).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Lead::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreLeadRequest extends FormRequest
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
            'registry_id' => ['required', 'integer', Rule::exists('registries', 'id')],
            'campaign_id' => ['required', 'integer', Rule::exists('campaigns', 'id')],
            'operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'source_id' => ['nullable', 'integer', Rule::exists('sources', 'id')],
            'operator_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'lead_status_id' => ['nullable', 'integer', Rule::exists('lead_statuses', 'id')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'extra_fields' => ['nullable', 'array'],
            'extra_fields.*' => ['string'],
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateLeadData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateLeadData::fromValidated($validated);
    }
}
