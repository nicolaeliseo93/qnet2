<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Http\Requests\Concerns\ValidatesRequestClientProfile;
use App\Http\Requests\Concerns\ValidatesWorkflowStatus;
use App\Models\Opportunity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /api/request-management/{opportunity} (spec 0049 data_contract):
 * sparse payload, only the submitted keys are ever touched.
 *
 * `authorize()` is a pass-through: the resource authorization
 * (`request-management.update`) AND the D-3 manager-scoping guard
 * (RequestManagementScope) both need the resolved {opportunity} route
 * parameter, so they run in the controller (mirrors OpportunityController's
 * own thin-controller pattern), not here.
 *
 * `opportunity_workflow_status_id` reuses ValidatesWorkflowStatus verbatim
 * (spec 0047): membership is checked against the set resolved for the
 * route's PERSISTED opportunity — this module never submits
 * source_id/state_id/product_lines, so the trait's "submitted overrides,
 * else fall back to $current" behaviour always falls back to the opportunity
 * as-is.
 *
 * `attribute_values` deep validation (per-code applicability/type/required,
 * spec 0049 D-4) is intentionally NOT duplicated here: it runs inside
 * RequestManagementService::updateWork() via AttributeValueValidator, the
 * single place that also resolves the applicable set and merges the map —
 * doing it twice would mean resolving CategoryHierarchy::effectiveAttributes()
 * an extra time for no benefit. Its ValidationException (keyed
 * `attribute_values.<code>`) surfaces as the same 422 shape either way
 * (BaseApiController::handleControllerException).
 *
 * `client_contacts`/`client_address` (spec 0049 amendment) come from
 * ValidatesRequestClientProfile: the client anagraphic block the panel edits
 * inline, written on the Registry's PersonalData card. Same sparse rule as
 * every other key — absent means untouched.
 */
class UpdateRequestRequest extends FormRequest
{
    use ValidatesRequestClientProfile, ValidatesWorkflowStatus;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opportunity_workflow_status_id' => ['sometimes', 'nullable', 'integer', 'exists:opportunity_workflow_statuses,id'],
            'attribute_values' => ['sometimes', 'array'],
            'next_callback_at' => ['sometimes', 'nullable', 'date'],
            ...$this->clientProfileRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Opportunity $opportunity */
            $opportunity = $this->route('opportunity');

            $this->validateWorkflowStatus($validator, $opportunity);
            $this->validateClientProfile($validator);
        });
    }
}
