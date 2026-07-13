<?php

namespace App\Http\Requests\Campaigns;

use App\DataObjects\Campaigns\UpdateCampaignData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Campaign;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/campaigns/{campaign} (spec 0023).
 * Every field is `sometimes`; `code` is intentionally NOT a rule (BR-1).
 *
 * BR-2 (campaign-derivation) on a partial update depends on the EFFECTIVE
 * `project_id` after this request (submitted value, or — when `project_id`
 * is not touched — the campaign's current one):
 *  - effectively linked → the 4 classification fields are `prohibited`
 *    (reject an explicit value, mirroring the store rule);
 *  - effectively standalone because THIS request unlinks it (was linked,
 *    now null) → they are `required`: the previous values are NULL in DB
 *    (BR-2), so fresh ones must be supplied in the same request (spec:
 *    "da linked a standalone le rende obbligatorie");
 *  - effectively standalone and UNCHANGED (already standalone, project_id
 *    not touched) → `sometimes`+`required`: optional to resubmit, but a
 *    submitted value cannot be emptied, mirroring UpdateProjectRequest's
 *    mandatory-field pattern.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $campaign)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $campaign.
 */
class UpdateCampaignRequest extends FormRequest
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
        return [
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'description' => ['sometimes', 'nullable', 'string'],
            'registry_id' => ['sometimes', 'nullable', 'integer', Rule::exists('registries', 'id')],
            'source_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sources', 'id')],
            'partner_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'project_status_id' => $this->derivedFieldRules('project_statuses'),
            'business_function_id' => $this->derivedFieldRules('business_functions'),
            'state_id' => $this->derivedFieldRules('states'),
            'product_category_id' => $this->derivedFieldRules('product_categories'),
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'total_budget' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'target_lead' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * The validation rule set for one of the 4 BR-2 classification fields,
     * shared here since only the target `exists` table differs between them.
     *
     * @return array<int, mixed>
     */
    private function derivedFieldRules(string $existsTable): array
    {
        return match (true) {
            $this->isLinkedAfterUpdate() => ['prohibited'],
            $this->isUnlinkingFromProject() => ['required', 'integer', Rule::exists($existsTable, 'id')],
            default => ['sometimes', 'required', 'integer', Rule::exists($existsTable, 'id')],
        };
    }

    /**
     * Whether the campaign will be linked to a project once this update is
     * applied: the submitted `project_id` when present, else the campaign's
     * current one.
     */
    private function isLinkedAfterUpdate(): bool
    {
        if ($this->has('project_id')) {
            return $this->filled('project_id');
        }

        return $this->currentCampaign()->project_id !== null;
    }

    /**
     * Whether THIS request is the one unlinking a previously-linked campaign
     * (project_id explicitly cleared): the moment its 4 classification
     * fields, NULL in DB until now, need fresh values.
     */
    private function isUnlinkingFromProject(): bool
    {
        return $this->has('project_id')
            && ! $this->filled('project_id')
            && $this->currentCampaign()->project_id !== null;
    }

    private function currentCampaign(): Campaign
    {
        /** @var Campaign $campaign */
        $campaign = $this->route('campaign');

        return $campaign;
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
        return $this->currentCampaign();
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateCampaignData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateCampaignData::fromValidated($validated);
    }
}
