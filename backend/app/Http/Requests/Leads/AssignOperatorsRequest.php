<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadAssignmentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/leads/assign-operators (spec 0048): bulk-assign a Sede
 * and an Operatore to many REAL leads at once, either to a single chosen
 * operator (`mode=single`) or load-balanced across the Sede's operators
 * (`mode=balanced`, LeadOperatorDistributor). `operator_id` is required only
 * in `single` mode.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller, per lead, via LeadPolicy — same convention as
 * StoreLeadRequest/UpdateLeadRequest).
 */
class AssignOperatorsRequest extends FormRequest
{
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
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', Rule::exists('leads', 'id')],
            'operational_site_id' => ['required', 'integer', Rule::exists('operational_sites', 'id')],
            'mode' => ['required', Rule::enum(LeadAssignmentMode::class)],
            'operator_id' => ['required_if:mode,single', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * The submitted lead ids, deduplicated.
     *
     * @return array<int, int>
     */
    public function leadIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('lead_ids', []);

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    public function operationalSiteId(): int
    {
        return (int) $this->validated('operational_site_id');
    }

    public function mode(): LeadAssignmentMode
    {
        return LeadAssignmentMode::from((string) $this->validated('mode'));
    }

    public function operatorId(): ?int
    {
        $value = $this->validated('operator_id');

        return $value === null ? null : (int) $value;
    }
}
