<?php

namespace App\Http\Requests\Campaigns;

use App\DataObjects\Campaigns\CreateCampaignData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/campaigns (spec 0023). `code` is
 * intentionally NOT a rule: it is server-generated (BR-1), so any submitted
 * value is silently dropped by validated() and never reaches the DTO
 * (AC-029).
 *
 * BR-2 (campaign-derivation) is enforced right here, value-level: when
 * `project_id` is submitted and non-null, the 4 classification fields
 * (project_status_id/business_function_id/state_id/product_category_id) are
 * `prohibited` — present-with-a-value fails validation (AC-022); otherwise
 * they are `required` (AC-023). `filled()` treats both "key absent" and
 * "key present but null" as NOT linked, matching the data contract's
 * `project_id?: int|null`.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Campaign::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreCampaignRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via CampaignPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $linked = $this->filled('project_id');

        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'registry_id' => ['nullable', 'integer', Rule::exists('registries', 'id')],
            'source_id' => ['nullable', 'integer', Rule::exists('sources', 'id')],
            'partner_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'project_status_id' => $linked
                ? ['prohibited']
                : ['required', 'integer', Rule::exists('project_statuses', 'id')],
            'business_function_id' => $linked
                ? ['prohibited']
                : ['required', 'integer', Rule::exists('business_functions', 'id')],
            'state_id' => $linked
                ? ['prohibited']
                : ['required', 'integer', Rule::exists('states', 'id')],
            'product_category_id' => $linked
                ? ['prohibited']
                : ['required', 'integer', Rule::exists('product_categories', 'id')],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'target_lead' => ['nullable', 'integer', 'min:0'],
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
        return 'campaigns';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateCampaignData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateCampaignData::fromValidated($validated);
    }
}
