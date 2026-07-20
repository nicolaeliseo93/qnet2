<?php

namespace App\Http\Requests\Opportunities;

use App\DataObjects\Opportunities\UpdateOpportunityData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesManagerSlots;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/opportunities/{opportunity}
 * (spec 0040). Every field is `sometimes` (partial PATCH), never null once
 * touched for `name` (mandatory) — `opportunity_status_id` (spec 0043, D-3)
 * joins that never-null set: `sometimes|required`, the FK cannot be cleared.
 * `lead_id` is ALWAYS `prohibited` (BR-2,
 * immutable once set). When $opportunity carries a `lead_id`, its 2
 * BR-1-derivable fields are re-resolved against the CURRENT lead/campaign
 * state (LeadOpportunityDefaultsResolver — same source as create): a
 * submission of one of them must equal that current derived value
 * (`Rule::in`) — a DIFFERENT value 422s, the SAME value is a no-op (BR-2).
 * `source_id` stays optional even unlocked. An opportunity with no lead has
 * none of these fields locked. `referent_id` is NOT derivable (spec 0041
 * D-1/D-3): it stays a plain, always-editable field scoped to the chosen
 * registry (BR-4, spec 0040).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $opportunity)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $opportunity.
 *
 * Amendment rev.3: `business_function_id`/`product_category_id` are REPLACED
 * by `product_lines` (ValidatesProductLines) — no longer BR-1-derivable/
 * lockable scalars, a full-replace sync like `manager_slots`. User directive
 * 2026-07-17: `product_lines` may be OMITTED (partial PATCH, rows untouched)
 * but may NOT be cleared to `[]` (`min:1`) — an opportunity always keeps at
 * least one {business_function_id, product_category_id} row.
 * `company_id`/`company_site_id`/`operational_site_id` are REMOVED entirely.
 */
class UpdateOpportunityRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesManagerSlots;
    use ValidatesProductLines;

    public function authorize(): bool
    {
        // Authorization handled in the controller via OpportunityPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Opportunity $opportunity */
        $opportunity = $this->route('opportunity');
        $locked = $this->currentLockedValues($opportunity);

        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'registry_id' => $this->lockableRule($locked, 'registry_id', 'registries'),
            'referent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'commercial_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'supervisor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'source_id' => $this->lockableRule($locked, 'source_id', 'sources'),
            'opportunity_status_id' => ['sometimes', 'required', 'integer', Rule::exists('opportunity_statuses', 'id')],
            'lead_id' => ['prohibited'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'success_probability' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
        ], $this->managerSlotsRules(), $this->productLinesRules(required: false));
    }

    /**
     * One of the 2 BR-1-derivable fields: when $locked carries a value for
     * it, the submission must match EXACTLY (`Rule::in`, BR-2); otherwise the
     * plain relation rule applies — `required` only for `registry_id`'s own
     * unlocked case, `nullable` for `source_id` (never forced on an unlocked,
     * previously-empty field).
     *
     * @param  array<string, int>  $locked
     * @return array<int, mixed>
     */
    private function lockableRule(array $locked, string $field, string $table, bool $required = false): array
    {
        if (array_key_exists($field, $locked)) {
            return ['sometimes', 'integer', Rule::in([$locked[$field]])];
        }

        return $required
            ? ['sometimes', 'required', 'integer', Rule::exists($table, 'id')]
            : ['sometimes', 'nullable', 'integer', Rule::exists($table, 'id')];
    }

    /**
     * $opportunity's currently-locked field values (BR-2), re-resolved
     * against the CURRENT lead/campaign state — empty when the opportunity
     * has no lead (or it has since been removed, defence in depth).
     *
     * @return array<string, int>
     */
    private function currentLockedValues(Opportunity $opportunity): array
    {
        if ($opportunity->lead_id === null) {
            return [];
        }

        $lead = Lead::find($opportunity->lead_id);

        if ($lead === null) {
            return [];
        }

        $defaults = app(LeadOpportunityDefaultsResolver::class)->resolve($lead);

        return Arr::only($defaults->values, $defaults->lockedFields);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateManagerSlots($validator);
            $this->validateProductLines($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'opportunities';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Model $opportunity */
        $opportunity = $this->route('opportunity');

        return $opportunity;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateOpportunityData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateOpportunityData::fromValidated($validated);
    }
}
